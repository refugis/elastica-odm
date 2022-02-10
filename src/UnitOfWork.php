<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica;

use Doctrine\Common\Comparable;
use Doctrine\Common\EventManager;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectManagerAware;
use Elastica\Document;
use Kcs\Metadata\PropertyMetadata;
use ProxyManager\Proxy\LazyLoadingInterface;
use Refugis\ODM\Elastica\Events\LifecycleEventManager;
use Refugis\ODM\Elastica\Events\PreFlushEventArgs;
use Refugis\ODM\Elastica\Exception\DocumentNotManagedException;
use Refugis\ODM\Elastica\Exception\IndexNotFoundException;
use Refugis\ODM\Elastica\Exception\InvalidArgumentException;
use Refugis\ODM\Elastica\Exception\InvalidIdentifierException;
use Refugis\ODM\Elastica\Exception\ReadOnlyRequiresManagedDocumentException;
use Refugis\ODM\Elastica\Exception\UnexpectedDocumentStateException;
use Refugis\ODM\Elastica\Id\AssignedIdGenerator;
use Refugis\ODM\Elastica\Id\GeneratorInterface;
use Refugis\ODM\Elastica\Id\IdentityGenerator;
use Refugis\ODM\Elastica\Internal\CommitOrderCalculator;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Metadata\EmbeddedMetadata;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;
use Refugis\ODM\Elastica\Persister\DocumentPersister;
use Refugis\ODM\Elastica\Util\ClassUtil;
use RuntimeException;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function assert;
use function get_class;
use function is_array;
use function method_exists;
use function spl_object_id;

final class UnitOfWork
{
    public const STATE_MANAGED = 1;
    public const STATE_NEW = 2;
    public const STATE_DETACHED = 3;
    public const STATE_REMOVED = 4;

    /**
     * Map documents by identifiers.
     *
     * @var array<string, array<array-key, object>>
     */
    private array $identityMap = [];

    /**
     * Map of all attached documents by object hash.
     *
     * @var object[]
     */
    private array $objects = [];

    /**
     * Map of the original document data of managed documents.
     * Keys are object hash. This is used for calculating changesets at commit time.
     *
     * @var array<array-key, mixed>
     */
    private array $originalDocumentData = [];

    /**
     * Map of the document states.
     * Keys are object hash. Note that only MANAGED and REMOVED states are known,
     * as DETACHED documents can be gc'd and the associated hashes can be re-used.
     *
     * @var array<array-key, int>
     */
    private array $documentStates = [];

    /**
     * Map of document persister by class name.
     *
     * @var DocumentPersister[]
     */
    private array $documentPersisters = [];

    /**
     * The document manager associated with this unit of work.
     */
    private DocumentManagerInterface $manager;

    /**
     * The current event manager.
     */
    private EventManager $evm;

    /**
     * The current lifecycle event manager.
     */
    private LifecycleEventManager $lifecycleEventManager;

    /**
     * Map of pending document deletions.
     *
     * @var array<array-key, object>
     */
    private array $documentDeletions = [];

    /**
     * Map of pending document insertions.
     *
     * @var array<array-key, object>
     */
    private array $documentInsertions = [];

    /**
     * Map of pending document updates.
     *
     * @var array<array-key, object>
     */
    private array $documentUpdates = [];

    /**
     * Map of read-only document.
     * Keys are the object hash.
     *
     * @var array<array-key, object>
     */
    private array $readOnlyObjects = [];

    /**
     * Maps of document change sets.
     * Keys are the object hash.
     *
     * @var array<array-key, array<string, mixed>>
     */
    private array $documentChangeSets = [];

    public function __construct(DocumentManagerInterface $manager)
    {
        $this->manager = $manager;
        $this->evm = $manager->getEventManager();
        $this->lifecycleEventManager = new LifecycleEventManager($this, $this->evm);
    }

    /**
     * Clears the unit of work.
     * If document class is given, only documents of that class will be detached.
     */
    public function clear(?string $documentClass = null): void
    {
        if ($documentClass === null) {
            $this->identityMap =
            $this->objects =
            $this->documentStates =
            $this->documentPersisters =
            $this->documentDeletions =
            $this->documentUpdates =
            $this->documentChangeSets =
            $this->readOnlyObjects =
            $this->originalDocumentData = [];
        } else {
            $this->clearIdentityMapForDocumentClass($documentClass);
            $this->clearEntityInsertionsForDocumentClass($documentClass);
        }

        if (! $this->evm->hasListeners(Events::onClear)) {
            return;
        }

        $this->evm->dispatchEvent(Events::onClear, new Events\OnClearEventArgs($this->manager, $documentClass));
    }

    /**
     * Gets the document persister for a given document class.
     */
    public function getDocumentPersister(string $documentClass): DocumentPersister
    {
        $metadata = $this->manager->getClassMetadata($documentClass);
        assert($metadata instanceof DocumentMetadata);

        return $this->documentPersisters[$documentClass] ??= new DocumentPersister($this->manager, $metadata);
    }

    /**
     * Gets the object manager that owns this unit of work.
     */
    public function getObjectManager(): ObjectManager
    {
        return $this->manager;
    }

    /**
     * Searches for a document in the identity map and returns it if found.
     * Returns null otherwise.
     *
     * @param mixed $id
     */
    public function tryGetById($id, DocumentMetadata $class): ?object
    {
        return $this->identityMap[$class->name][(string) $id] ?? null;
    }

    /**
     * Checks if a document is attached to this unit of work.
     */
    public function isInIdentityMap(object $object): bool
    {
        $oid = spl_object_id($object);
        if (! isset($this->objects[$oid])) {
            return false;
        }

        $class = $this->getClassMetadata($object);
        $id = $class->getSingleIdentifier($object);

        if (empty($id)) {
            return false;
        }

        return isset($this->identityMap[$class->name][$id]);
    }

    /**
     * Gets the document state.
     */
    public function getDocumentState(object $document, ?int $assume = null): int
    {
        $oid = spl_object_id($document);

        if (isset($this->documentStates[$oid])) {
            return $this->documentStates[$oid];
        }

        if ($assume !== null) {
            return $assume;
        }

        // State here can only be NEW or DETACHED, as MANAGED and REMOVED states are known.
        $class = $this->getClassMetadata($document);
        $id = $class->getSingleIdentifier($document);

        if (empty($id)) {
            return self::STATE_NEW;
        }

        if ($this->tryGetById($id, $class)) {
            return self::STATE_DETACHED;
        }

        $persister = $this->getDocumentPersister($class->name);
        if ($persister->exists(['_id' => $id])) {
            return self::STATE_DETACHED;
        }

        return self::STATE_NEW;
    }

    /**
     * Commits all the operations pending in this unit of work.
     */
    public function commit(): void
    {
        if ($this->evm->hasListeners(Events::preFlush)) {
            $this->evm->dispatchEvent(Events::preFlush, new PreFlushEventArgs($this->manager));
        }

        $this->computeChangeSets();

        if (
            ! (
            $this->documentInsertions ||
            $this->documentDeletions ||
            $this->documentUpdates ||
            $this->documentChangeSets
            )
        ) {
            // Nothing to do.
            $this->dispatchOnFlush();
            $this->dispatchPostFlush();

            return;
        }

        $classOrder = $this->getCommitOrder();

        $this->dispatchOnFlush();

        foreach ($classOrder as $className) {
            $this->executeInserts($className);
            $this->executeUpdates($className);
            $this->executeDeletions($className);

            $this->getDocumentPersister($className)->refreshCollection();
        }

        $this->dispatchPostFlush();
        $this->postCommitCleanup();
    }

    public function computeChangeSets(): void
    {
        $this->computeScheduledInsertsChangeSets();

        foreach ($this->identityMap as $className => $documents) {
            $class = $this->manager->getClassMetadata($className);
            assert($class instanceof DocumentMetadata);

            if ($class->isReadOnly) {
                continue;
            }

            foreach ($documents as $document) {
                if ($document instanceof LazyLoadingInterface && ! $document->isProxyInitialized()) {
                    continue;
                }

                $oid = spl_object_id($document);
                if (isset($this->documentInsertions[$oid]) || isset($this->documentDeletions[$oid]) || ! isset($this->documentStates[$oid])) {
                    continue;
                }

                $this->computeChangeSet($class, $document);
            }
        }
    }

    /**
     * INTERNAL:
     * Computes the changeset of an individual document, independently of the
     * computeChangeSets() routine that is used at the beginning of a UnitOfWork#commit().
     *
     * The passed entity must be a managed entity. If the entity already has a change set
     * because this method is invoked during a commit cycle then the change sets are added.
     * whereby changes detected in this method prevail.
     *
     * @param object $document the entity for which to (re)calculate the change set
     *
     * @throws InvalidArgumentException if the passed entity is not MANAGED.
     *
     * @ignore
     */
    public function recomputeSingleDocumentChangeset(object $document): void
    {
        $oid = spl_object_id($document);
        if (! isset($this->documentStates[$oid]) || $this->documentStates[$oid] !== self::STATE_MANAGED) {
            throw new DocumentNotManagedException($document);
        }

        $class = $this->getClassMetadata($document);
        $actualData = [];
        $joinFieldName = $class->join['fieldName'] ?? null;
        foreach ($class->attributesMetadata as $field) {
            if ($field instanceof FieldMetadata && $field->isStored()) {
                if ($field->fieldName === $class->parentField) {
                    $parentField = $class->getAttributeMetadata($class->parentField);
                    assert($parentField instanceof PropertyMetadata);

                    $refl = $parentField->getReflection();
                    $refl->setAccessible(true);

                    $parentObject = $refl->getValue($document);
                    $parentMetadata = $this->getClassMetadata($parentObject);
                    $parentId = $parentMetadata->getSingleIdentifier($parentObject);

                    $actualData[$joinFieldName] = ['name' => $class->join['type'], 'parent' => $parentId];
                } else {
                    $actualData[$field->fieldName] = $field->getValue($document);
                }
            } elseif ($field instanceof EmbeddedMetadata) {
                $actualData[$field->fieldName] = $field->getValue($document);
            }
        }

        if ($joinFieldName !== null && $class->parentField === null) {
            $actualData[$joinFieldName] = ['name' => $class->join['type']];
        }

        if (! isset($this->originalDocumentData[$oid])) {
            throw new RuntimeException('Cannot call recomputeSingleDocumentChangeset before computeChangeSet on a document.');
        }

        $originalData = $this->originalDocumentData[$oid];
        $changeSet = [];

        foreach ($actualData as $propName => $actualValue) {
            $orgValue = $originalData[$propName] ?? null;

            if ($orgValue === $actualValue) {
                continue;
            }

            $changeSet[$propName] = [$orgValue, $actualValue];
        }

        if (! $changeSet) {
            return;
        }

        if (isset($this->documentChangeSets[$oid])) {
            $this->documentChangeSets[$oid] = array_merge($this->documentChangeSets[$oid], $changeSet);
        } elseif (! isset($this->documentInsertions[$oid])) {
            $this->documentChangeSets[$oid] = $changeSet;
            $this->documentUpdates[$oid] = $document;
        }

        $this->originalDocumentData[$oid] = $actualData;
    }

    /**
     * Retrieve the computed changeset for a given document.
     *
     * @return array<string, mixed>
     */
    public function &getDocumentChangeSet(object $document): array
    {
        $oid = spl_object_id($document);
        $data = [];

        if (! isset($this->documentChangeSets[$oid])) {
            return $data;
        }

        return $this->documentChangeSets[$oid];
    }

    /**
     * Detaches a document from the unit of work.
     */
    public function detach(object $object): void
    {
        $visited = [];
        $this->doDetach($object, $visited);
    }

    /**
     * Persists a document as part of this unit of work.
     */
    public function persist(object $object): void
    {
        $visited = [];
        $this->doPersist($object, $visited);
    }

    /**
     * Removes a document as part of this unit of work.
     */
    public function remove(object $object): void
    {
        $visited = [];
        $this->doRemove($object, $visited);
    }

    /**
     * Merges the given document with the managed one.
     * Returns the managed copy of the document
     */
    public function merge(object $object): object
    {
        $visited = [];

        return $this->doMerge($object, $visited);
    }

    /**
     * INTERNAL:
     * Hydrates a document.
     *
     * @param Document   $document the elastica document containing the original data
     * @param object     $result   the resulting document object
     * @param bool       $embedded whether we are processing an embedded document
     *
     * @throws InvalidIdentifierException
     */
    public function createDocument(Document $document, object $result, bool $embedded = false): void
    {
        $class = $this->getClassMetadata($result);

        $typeManager = $this->manager->getTypeManager();
        $documentData = $document->getData();

        assert(is_array($documentData));

        // inject ObjectManager upon refresh.
        if ($result instanceof ObjectManagerAware) {
            $result->injectObjectManager($this->manager, $class);
        }

        foreach ($class->attributesMetadata as $fieldMetadata) {
            if (! $fieldMetadata instanceof FieldMetadata) {
                continue;
            }

            if ($fieldMetadata->identifier) {
                $fieldMetadata->setValue($result, $document->getId());
                continue;
            }

            if ($fieldMetadata->indexName) {
                $fieldMetadata->setValue($result, $document->getIndex());
                continue;
            }

            if ($fieldMetadata->typeName && method_exists($document, 'getType')) {
                $fieldMetadata->setValue($result, $document->getType());
                continue;
            }
        }

        foreach ($documentData as $key => &$value) {
            $field = $class->getField($key);

            if ($field instanceof EmbeddedMetadata) {
                if ($field->multiple) {
                    $value = array_map(function ($item) use ($field, $document) {
                        $embeddedObject = $field->newInstance();
                        $embeddedDocument = clone $document;
                        $embeddedDocument->setId('');
                        $embeddedDocument->setData($item);
                        $this->createDocument($embeddedDocument, $embeddedObject, true);

                        return $embeddedObject;
                    }, (array) $value);
                } else {
                    $embeddedObject = $field->newInstance();
                    $embeddedDocument = clone $document;
                    $embeddedDocument->setId('');
                    $embeddedDocument->setData($value);
                    $this->createDocument($embeddedDocument, $embeddedObject, true);

                    $value = $embeddedObject;
                }
            } elseif ($field instanceof FieldMetadata) {
                $fieldType = $typeManager->getType($field->type);
                if ($field->multiple) {
                    $value = array_map(static function ($item) use ($fieldType, $field) {
                        return $fieldType->toPHP($item, $field->options);
                    }, (array) $value);
                } else {
                    $value = $fieldType->toPHP($value, $field->options);
                }
            } elseif ($class->join !== null && $key === $class->join['fieldName'] && $class->parentField !== null) {
                $value = $this->manager->getReference($class->join['parentClass'], $value['parent']);
                $field = $class->getField($class->parentField);
            } else {
                continue;
            }

            $field->setValue($result, $value);
        }

        unset($value);
        if ($embedded) {
            return;
        }

        foreach ($class->getFieldNames() as $fieldName) {
            if (array_key_exists($fieldName, $documentData)) {
                continue;
            }

            $documentData[$fieldName] = null;
        }

        foreach ($class->embeddedFieldNames as $embeddedFieldName) {
            if (array_key_exists($embeddedFieldName, $documentData)) {
                $data = $documentData[$embeddedFieldName];
                if (is_array($data)) {
                    $data = array_map(static fn ($v) => clone $v, $data);
                } else {
                    $data = clone $data;
                }

                $documentData[$embeddedFieldName] = $data;
            } else {
                $documentData[$embeddedFieldName] = null;
            }
        }

        $this->originalDocumentData[spl_object_id($result)] = $documentData;
        $this->addToIdentityMap($result);
    }

    /**
     * INTERNAL:
     * Gets an id generator for the given type.
     *
     * @internal
     */
    public function getIdGenerator(int $generatorType): GeneratorInterface
    {
        static $generators = [];
        if (isset($generators[$generatorType])) {
            return $generators[$generatorType];
        }

        switch ($generatorType) {
            case DocumentMetadata::GENERATOR_TYPE_NONE:
                $generator = new AssignedIdGenerator();
                break;

            case DocumentMetadata::GENERATOR_TYPE_AUTO:
                $generator = new IdentityGenerator();
                break;

            default:
                throw new \InvalidArgumentException('Unknown id generator type ' . $generatorType);
        }

        return $generators[$generatorType] = $generator;
    }

    /**
     * Checks whether an entity is registered for insertion within this unit of work.
     */
    public function isScheduledForInsert(object $document): bool
    {
        return isset($this->documentInsertions[spl_object_id($document)]);
    }

    /**
     * Checks whether an entity is registered as removed/deleted with the unit
     * of work.
     */
    public function isScheduledForDelete(object $document): bool
    {
        return isset($this->documentDeletions[spl_object_id($document)]);
    }

    /**
     * INTERNAL:
     * Registers a document as managed.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidIdentifierException
     */
    public function registerManaged(object $document, array $data): void
    {
        $oid = spl_object_id($document);

        $this->documentStates[$oid] = self::STATE_MANAGED;
        $this->originalDocumentData[$oid] = $data;

        $this->addToIdentityMap($document);
    }

    /**
     * Marks a document as read-only so that it will not be considered for updates during UnitOfWork#commit().
     *
     * This operation cannot be undone as some parts of the UnitOfWork now keep gathering information
     * on this object that might be necessary to perform a correct update.
     *
     * @throws InvalidArgumentException
     */
    public function markReadOnly(object $object): void
    {
        if (! $this->isInIdentityMap($object)) {
            throw new ReadOnlyRequiresManagedDocumentException($object);
        }

        $this->readOnlyObjects[spl_object_id($object)] = true;
    }

    /**
     * Is this document read only?
     *
     * @throws InvalidArgumentException
     */
    public function isReadOnly(object $object): bool
    {
        return isset($this->readOnlyObjects[spl_object_id($object)]);
    }

    /**
     * Clears the identity map for the given document class.
     */
    private function clearIdentityMapForDocumentClass(string $documentClass): void
    {
        if (! isset($this->identityMap[$documentClass])) {
            return;
        }

        $visited = [];

        foreach ($this->identityMap[$documentClass] as $document) {
            $this->doDetach($document, $visited);
        }
    }

    /**
     * Clears the document insertions for the given document class.
     */
    private function clearEntityInsertionsForDocumentClass(string $documentClass): void
    {
        foreach ($this->documentInsertions as $hash => $document) {
            // note: performance optimization - `instanceof` is much faster than a function call
            if (! ($document instanceof $documentClass) || get_class($document) !== $documentClass) {
                continue;
            }

            unset($this->documentInsertions[$hash]);
        }
    }

    /**
     * Computes the changes that happened to a single document.
     */
    private function computeChangeSet(DocumentMetadata $class, object $document): void
    {
        $oid = spl_object_id($document);
        if (isset($this->readOnlyObjects[$oid])) {
            return;
        }

        $actualData = [];
        $joinFieldName = $class->join['fieldName'] ?? null;
        foreach ($class->attributesMetadata as $field) {
            if ($field instanceof FieldMetadata && $field->isStored()) {
                if ($field->fieldName === $class->parentField) {
                    $parentField = $class->getAttributeMetadata($class->parentField);
                    assert($parentField instanceof PropertyMetadata);

                    $refl = $parentField->getReflection();
                    $refl->setAccessible(true);

                    $parentObject = $refl->getValue($document);
                    $parentMetadata = $this->getClassMetadata($parentObject);
                    $parentId = $parentMetadata->getSingleIdentifier($parentObject);

                    $actualData[$joinFieldName] = ['name' => $class->join['type'], 'parent' => $parentId];
                } else {
                    $actualData[$field->fieldName] = $field->getValue($document);
                }
            } elseif ($field instanceof EmbeddedMetadata) {
                $actualData[$field->fieldName] = $field->getValue($document);
            }
        }

        if ($joinFieldName !== null && $class->parentField === null) {
            $actualData[$joinFieldName] = ['name' => $class->join['type']];
        }

        if (! isset($this->originalDocumentData[$oid])) {
            // Entity is either NEW or MANAGED but not yet fully persisted.
            $this->originalDocumentData[$oid] = $actualData;

            $changeSet = [];
            foreach ($actualData as $field => $value) {
                $changeSet[$field] = [null, $value];
            }

            $this->documentChangeSets[$oid] = $changeSet;
        } else {
            // Document is MANAGED
            $originalData = $this->originalDocumentData[$oid];
            $changeSet = [];

            if (! $document instanceof LazyLoadingInterface || $document->isProxyInitialized()) {
                foreach ($actualData as $propName => $actualValue) {
                    // skip field, its a partially omitted one!
                    if (! array_key_exists($propName, $originalData)) {
                        continue;
                    }

                    $orgValue = $originalData[$propName];

                    // skip if value haven't changed
                    if ($orgValue === $actualValue) {
                        continue;
                    }

                    if (
                        $orgValue instanceof Comparable &&
                        $actualValue instanceof Comparable &&
                        $orgValue->compareTo($actualValue) === 0
                    ) {
                        continue;
                    }

                    $changeSet[$propName] = [$orgValue, $actualValue];
                }
            }

            if ($changeSet) {
                $this->documentChangeSets[$oid] = $changeSet;
                $this->originalDocumentData[$oid] = $actualData;
                $this->documentUpdates[$oid] = $document;
            }
        }
    }

    /**
     * Adds a document to the identity map.
     * The identifier MUST be set before trying to add the document or
     * this method will throw an InvalidIdentifierException.
     *
     * @throws InvalidIdentifierException
     */
    private function addToIdentityMap(object $object): void
    {
        $oid = spl_object_id($object);
        $class = $this->getClassMetadata($object);
        $id = $class->getSingleIdentifier($object);

        if (empty($id)) {
            throw new InvalidIdentifierException('Documents must have an identifier in order to be added to the identity map.');
        }

        $this->objects[$oid] = $object;
        $this->identityMap[$class->name][$id] = $object;
        $this->documentStates[$oid] = self::STATE_MANAGED;
    }

    /**
     * Removes an object from identity map.
     *
     * @throws InvalidIdentifierException
     */
    private function removeFromIdentityMap(object $object): void
    {
        $class = $this->getClassMetadata($object);
        $id = $class->getSingleIdentifier($object);

        if (empty($id)) {
            throw new InvalidIdentifierException('Documents must have an identifier in order to be added to the identity map.');
        }

        unset($this->identityMap[$class->name][$id], $this->readOnlyObjects[$class->name][$id]);
    }

    /**
     * Executes a persist operation.
     *
     * @param array<string, bool> $visited
     *
     * @throws InvalidArgumentException if document state is equal to NEW.
     */
    private function doPersist(object $object, array &$visited): void
    {
        $oid = spl_object_id($object);
        if (isset($visited[$oid])) {
            return;
        }

        $visited[$oid] = true;
        $class = $this->getClassMetadata($object);

        $documentState = $this->getDocumentState($object, self::STATE_NEW);
        switch ($documentState) {
            case self::STATE_MANAGED:
                break;

            case self::STATE_NEW:
                $this->persistNew($class, $object);
                break;

            case self::STATE_REMOVED:
                unset($this->documentDeletions[$oid]);
                $this->addToIdentityMap($object);
                $this->documentStates[$oid] = self::STATE_MANAGED;

                break;
        }

        $this->cascadePersist($object, $visited);
    }

    /**
     * Executes a remove operation.
     *
     * @param array<string, bool> $visited
     *
     * @throws InvalidArgumentException if document state is equal to NEW.
     */
    private function doRemove(object $object, array &$visited): void
    {
        $oid = spl_object_id($object);
        if (isset($visited[$oid])) {
            return;
        }

        $visited[$oid] = true;

        // Cascade first to avoid problems with proxy initializing out of the identity map.
        $this->cascadeRemove($object, $visited);

        $class = $this->getClassMetadata($object);
        $documentState = $this->getDocumentState($object);

        switch ($documentState) {
            case self::STATE_NEW:
            case self::STATE_REMOVED:
                // Nothing to do.
                break;

            case self::STATE_MANAGED:
                $this->lifecycleEventManager->preRemove($class, $object);
                $this->scheduleForDeletion($object);
                break;

            case self::STATE_DETACHED:
                throw new InvalidArgumentException('Detached document cannot be removed');

            default:
                throw new UnexpectedDocumentStateException($documentState);
        }
    }

    /**
     * Executes a merge operation on a document.
     *
     * @param array<string, bool> $visited
     *
     * @throws \InvalidArgumentException if document state is equal to NEW.
     */
    private function doMerge(object $object, array &$visited): object
    {
        $oid = spl_object_id($object);
        if (isset($visited[$oid])) {
            return $visited[$oid];
        }

        $class = $this->getClassMetadata($object);
        $managedCopy = $visited[$oid] = $object;

        assert($class instanceof DocumentMetadata);

        if ($this->getDocumentState($object, self::STATE_DETACHED) !== self::STATE_MANAGED) {
            $this->manager->initializeObject($object);

            $id = $class->getSingleIdentifier($object);
            $managedCopy = null;

            if ($id !== null) {
                try {
                    $managedCopy = $this->manager->find($class->name, $id);
                } catch (IndexNotFoundException $e) {
                    // @ignoreException
                    // Index does not exists, will be created.
                }

                if ($managedCopy !== null) {
                    if ($this->getDocumentState($managedCopy) === self::STATE_REMOVED) {
                        throw new InvalidArgumentException('Removed document detected during merge.');
                    }

                    $this->manager->initializeObject($managedCopy);
                }
            }

            if ($managedCopy === null) {
                $managedCopy = $this->newInstance($class);
                if ($id !== null) {
                    $class->setIdentifierValue($managedCopy, $id);
                }

                $this->persistNew($class, $managedCopy);
            }

            foreach ($class->getReflectionClass()->getProperties() as $property) {
                $name = $property->name;
                $property->setAccessible(true);

                if (! isset($class->associationMappings[$name])) {
                    if (! $class->isIdentifier($name)) {
                        $property->setValue($managedCopy, $property->getValue($object));
                    }
                }

                // todo: associations
            }
        }

        $visited[spl_object_id($managedCopy)] = $managedCopy;
        $this->cascadeMerge($object, $managedCopy, $visited);

        return $managedCopy;
    }

    /**
     * Execute detach operation.
     *
     * @param array<string, bool> $visited
     *
     * @throws InvalidIdentifierException
     */
    private function doDetach(object $object, array &$visited): void
    {
        $oid = spl_object_id($object);
        if (isset($visited[$oid])) {
            return;
        }

        $visited[$oid] = true;

        $state = $this->getDocumentState($object, self::STATE_DETACHED);
        if ($state !== self::STATE_MANAGED) {
            return;
        }

        unset(
            $this->documentStates[$oid],
            $this->objects[$oid],
            $this->originalDocumentData[$oid]
        );

        $this->removeFromIdentityMap($object);
        $this->cascadeDetach($object, $visited);
    }

    private function persistNew(DocumentMetadata $class, object $object): void
    {
        if (! $class->document) {
            return;
        }

        $this->lifecycleEventManager->prePersist($class, $object);
        $oid = spl_object_id($object);

        assert($class->identifier !== null);
        $idGenerator = $this->getIdGenerator($class->idGeneratorType);
        if (! $idGenerator->isPostInsertGenerator()) {
            $id = $idGenerator->generate($this->manager, $object);
            $class->setIdentifierValue($object, $id);
        }

        $this->documentStates[$oid] = self::STATE_MANAGED;
        $this->scheduleForInsert($object);
    }

    /**
     * @param array<string, bool> $visited
     */
    private function cascadeDetach(object $object, array &$visited): void
    {
        // @todo
    }

    /**
     * @param array<string, bool> $visited
     */
    private function cascadeMerge(object $object, object $managedCopy, array &$visited): void
    {
        // @todo
    }

    /**
     * @param array<string, bool> $visited
     */
    private function cascadeRemove(object $object, array &$visited): void
    {
        // @todo
    }

    /**
     * @param array<string, bool> $visited
     */
    private function cascadePersist(object $object, array &$visited): void
    {
        // @todo
    }

    /**
     * Schedule a document for insertion.
     * If the document already has an identifier it will be added to the identity map.
     *
     * @throws InvalidIdentifierException
     */
    private function scheduleForInsert(object $object): void
    {
        $oid = spl_object_id($object);
        $class = $this->getClassMetadata($object);

        $this->documentInsertions[$oid] = $object;

        if ($class->getSingleIdentifier($object) === null) {
            return;
        }

        $this->addToIdentityMap($object);
    }

    /**
     * Schedule a document for deletion.
     *
     * @throws InvalidIdentifierException
     */
    private function scheduleForDeletion(object $object): void
    {
        $oid = spl_object_id($object);
        if (isset($this->documentInsertions[$oid])) {
            if ($this->isInIdentityMap($object)) {
                $this->removeFromIdentityMap($object);
            }

            unset($this->documentInsertions[$oid], $this->documentStates[$oid]);

            return;
        }

        if (! $this->isInIdentityMap($object)) {
            return;
        }

        $this->removeFromIdentityMap($object);
        unset($this->documentUpdates[$oid]);

        $this->documentDeletions[$oid] = $object;
        $this->documentStates[$oid] = self::STATE_REMOVED;
    }

    private function dispatchOnFlush(): void
    {
        // @todo
    }

    private function dispatchPostFlush(): void
    {
        // @todo
    }

    private function executeInserts(string $className): void
    {
        $inserts = array_filter($this->documentInsertions, fn (object $document): bool => $className === $this->getClassMetadata($document)->name);
        if (empty($inserts)) {
            return;
        }

        $classMetadata = $this->manager->getClassMetadata($className);
        $persister = $this->getDocumentPersister($className);
        $postInsertIds = $persister->bulkInsert($inserts);

        foreach (array_values($inserts) as $i => $document) {
            $postInsertId = $postInsertIds[$i];
            if ($postInsertId === null) {
                continue;
            }

            $id = $postInsertId->getId();
            $oid = spl_object_id($document);

            $classMetadata->setIdentifierValue($document, $id);
            $this->documentStates[$oid] = self::STATE_MANAGED;

            $this->addToIdentityMap($document);
            $this->lifecycleEventManager->postPersist($classMetadata, $document);
        }
    }

    private function executeUpdates(string $className): void
    {
        $updates = array_filter($this->documentUpdates, fn (object $document): bool => $className === $this->getClassMetadata($document)->name);
        if (empty($updates)) {
            return;
        }

        $classMetadata = $this->manager->getClassMetadata($className);
        $persister = $this->getDocumentPersister($className);

        $realUpdates = [];
        foreach ($updates as $oid => $document) {
            $this->lifecycleEventManager->preUpdate($classMetadata, $document);

            if (empty($this->documentChangeSets[$oid])) {
                continue;
            }

            $realUpdates[] = $document;
        }

        $persister->bulkUpdate($realUpdates);

        foreach ($updates as $oid => $document) {
            unset($this->documentUpdates[$oid], $this->documentChangeSets[$oid]);
            $this->lifecycleEventManager->postUpdate($classMetadata, $document);
        }
    }

    private function executeDeletions(string $className): void
    {
        foreach ($this->documentDeletions as $oid => $document) {
            $class = $this->getClassMetadata($document);
            if ($className !== $class->name) {
                continue;
            }

            $persister = $this->getDocumentPersister($class->name);
            $persister->delete($document);

            unset($this->documentDeletions[$oid], $this->originalDocumentData[$oid], $this->documentStates[$oid]);

            if ($class->idGeneratorType !== DocumentMetadata::GENERATOR_TYPE_NONE) {
                $class->setIdentifierValue($document, null);
            }

            $this->lifecycleEventManager->postRemove($class, $document);
        }
    }

    private function postCommitCleanup(): void
    {
        $this->documentDeletions =
        $this->documentInsertions =
        $this->documentUpdates =
        $this->documentChangeSets = [];
    }

    private function computeScheduledInsertsChangeSets(): void
    {
        foreach ($this->documentInsertions as $document) {
            $class = $this->getClassMetadata($document);
            $this->computeChangeSet($class, $document);
        }
    }

    /**
     * Creates a new instance of given class and inject object manager if needed.
     */
    private function newInstance(DocumentMetadata $class): object
    {
        $document = $class->newInstance();

        // inject ObjectManager upon refresh.
        if ($document instanceof ObjectManagerAware) {
            $document->injectObjectManager($this->manager, $class);
        }

        return $document;
    }

    /**
     * Calculates the commit order, based on associations
     * on document metadata.
     *
     * @return string[]
     */
    private function getCommitOrder(): array
    {
        static $calculator = null;
        if ($calculator === null) {
            $calculator = new CommitOrderCalculator();
        }

        $objects = array_merge($this->documentInsertions, $this->documentUpdates, $this->documentDeletions);

        $classes = [];
        foreach ($objects as $object) {
            $metadata = $this->getClassMetadata($object);
            $calculator->addClass($metadata);
            $classes[$metadata->getName()] = $metadata;
        }

        $order = $calculator->getOrder(array_keys($classes));

        return array_map(static function (array $element) {
            return $element[0]->getClassName();
        }, $order);
    }

    private function getClassMetadata(object $document): DocumentMetadata
    {
        $class = $this->manager->getClassMetadata(ClassUtil::getClass($document));
        assert($class instanceof DocumentMetadata);

        return $class;
    }
}
