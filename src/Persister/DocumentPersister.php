<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Persister;

use Elastica\Query;
use Refugis\ODM\Elastica\Collection\CollectionInterface;
use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Exception\ConversionFailedException;
use Refugis\ODM\Elastica\Exception\IndexNotFoundException;
use Refugis\ODM\Elastica\Hydrator\HydratorInterface;
use Refugis\ODM\Elastica\Id\PostInsertId;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Metadata\EmbeddedMetadata;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;
use Refugis\ODM\Elastica\Tools\SchemaGenerator;
use Refugis\ODM\Elastica\Util\ClassUtil;

use function array_map;
use function assert;
use function count;
use function implode;
use function str_replace;

class DocumentPersister
{
    private DocumentManagerInterface $dm;
    private DocumentMetadata $class;
    private CollectionInterface $collection;

    public function __construct(DocumentManagerInterface $dm, DocumentMetadata $class)
    {
        $this->dm = $dm;
        $this->class = $class;

        $this->collection = $dm->getCollection($class->name);
    }

    public function getClassMetadata(): DocumentMetadata
    {
        return $this->class;
    }

    /**
     * Finds a document by a set of criteria.
     *
     * @param array<string, mixed> $criteria query criteria
     * @param array<string, mixed> $hints
     * @param object $document The document to load data into. If not given, a new document will be created.
     *
     * @return object|null the loaded and managed document instance or null if no document was found
     */
    public function load(array $criteria, array $hints = [], ?object $document = null): ?object
    {
        $query = $this->prepareQuery($criteria);
        if ($hints[Hints::HINT_REFRESH] ?? false) {
            $params = $query->getParams();
            unset($params['_source']);
            $query->setParams($params);
        }

        $resultSet = $this->collection->search($query);
        if (! count($resultSet)) {
            return null;
        }

        $esDoc = $resultSet[0]->getDocument();

        if ($document !== null) {
            $this->dm->getUnitOfWork()->createDocument($esDoc, $document);

            return $document;
        }

        return $this->dm->newHydrator(HydratorInterface::HYDRATE_OBJECT)
            ->hydrateOne($esDoc, $this->class->name);
    }

    public function loadAll(array $criteria = [], ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $query = $this->prepareQuery($criteria);
        $search = $this->collection->createSearch($this->dm, $query);
        $search->setSort($orderBy);

        if ($limit === null && $offset === null) {
            $search->setScroll(true);
        } else {
            if ($limit !== null) {
                $search->setLimit($limit);
            }

            if ($offset !== null) {
                $search->setOffset($offset);
            }
        }

        return $search->execute();
    }

    /**
     * Checks whether a document matching criteria exists in collection.
     *
     * @param array<string, mixed> $criteria
     */
    public function exists(array $criteria): bool
    {
        $query = $this->prepareQuery($criteria);
        $query->setSize(0);
        $query->setParam('terminate_after', 1);

        return $this->collection->search($query)->count() > 0;
    }

    /**
     * Insert a document in the collection.
     */
    public function insert(object $document): ?PostInsertId
    {
        $class = $this->dm->getClassMetadata(ClassUtil::getClass($document));
        assert($class instanceof DocumentMetadata);
        $idGenerator = $this->dm->getUnitOfWork()->getIdGenerator($class->idGeneratorType);
        $postIdGenerator = $idGenerator->isPostInsertGenerator();

        $id = $postIdGenerator ? null : $class->getSingleIdentifier($document);
        $body = $this->prepareUpdateData($document)['body'];

        try {
            $response = $this->collection->create($id, $body);
        } catch (IndexNotFoundException $e) {
            $schemaGenerator = new SchemaGenerator($this->dm);
            $schema = $schemaGenerator->generateSchema()->getMapping()[$this->class->name] ?? null;

            if ($schema === null) {
                throw $e;
            }

            $this->collection->updateMapping($schema->getMapping());
            $response = $this->collection->create($id, $body);
        }

        $data = $response->getData();

        foreach ($class->attributesMetadata as $field) {
            if (! $field instanceof FieldMetadata) {
                continue;
            }

            if ($field->indexName) {
                $field->setValue($document, $data['_index'] ?? null);
            }

            if (! $field->typeName) {
                continue;
            }

            $field->setValue($document, $data['_type'] ?? null);
        }

        $postInsertId = null;
        if ($postIdGenerator) {
            $postInsertId = new PostInsertId($document, $this->collection->getLastInsertedId());
        }

        return $postInsertId;
    }

    /**
     * Updates a managed document.
     */
    public function update(object $document): void
    {
        $class = $this->dm->getClassMetadata(ClassUtil::getClass($document));
        $data = $this->prepareUpdateData($document);
        $id = $class->getSingleIdentifier($document);

        $this->collection->update((string) $id, $data['body'], $data['script']);
    }

    /**
     * Deletes a managed document.
     */
    public function delete(object $document): void
    {
        $class = $this->dm->getClassMetadata(ClassUtil::getClass($document));
        $id = $class->getSingleIdentifier($document);

        $this->collection->delete((string) $id);
    }

    /**
     * Refreshes the underlying collection.
     */
    public function refreshCollection(): void
    {
        $this->collection->refresh();
    }

    private function prepareQuery(array $criteria): Query
    {
        $bool = new Query\BoolQuery();
        foreach ($criteria as $key => $value) {
            $bool->addFilter(new Query\Term([$key => ['value' => $value]]));
        }

        return Query::create($bool);
    }

    /**
     * INTERNAL:
     * Prepares data for an update operation.
     *
     * @internal
     *
     * @return array<string, mixed>
     *
     * @throws ConversionFailedException
     */
    public function prepareUpdateData(object $document): array
    {
        $script = [];
        $body = [];

        $changeSet = $this->dm->getUnitOfWork()->getDocumentChangeSet($document);
        $class = $this->dm->getClassMetadata(ClassUtil::getClass($document));
        $typeManager = $this->dm->getTypeManager();

        foreach ($changeSet as $name => $value) {
            $field = $class->getField($name);
            if ($field instanceof EmbeddedMetadata) {
                if ($field->multiple) {
                    $body[$field->fieldName] = array_map(function ($item) use ($field) {
                        return $this->prepareEmbeddedUpdateData($item, $field);
                    }, (array) $value[1]);
                } elseif ($value[1] !== null) {
                    $body[$field->fieldName] = $this->prepareEmbeddedUpdateData($value[1], $field);
                } else {
                    $script[] = 'ctx._source.remove(\'' . str_replace('\'', '\\\'', $field->fieldName) . '\')';
                }
            } elseif ($field instanceof FieldMetadata) {
                $type = $typeManager->getType($field->type);

                if ($field->multiple) {
                    $body[$field->fieldName] = array_map(static function ($item) use ($type, $field) {
                        return $type->toDatabase($item, $field->options);
                    }, (array) $value[1]);
                } elseif ($value[1] !== null) {
                    $body[$field->fieldName] = $type->toDatabase($value[1], $field->options);
                } else {
                    $script[] = 'ctx._source.remove(\'' . str_replace('\'', '\\\'', $field->fieldName) . '\')';
                }
            }
        }

        return [
            'body' => $body,
            'script' => implode('; ', $script),
        ];
    }

    private function prepareEmbeddedUpdateData($value, EmbeddedMetadata $field): array
    {
        $class = $this->dm->getClassMetadata($field->targetClass);
        assert($class instanceof DocumentMetadata);

        $typeManager = $this->dm->getTypeManager();
        $properties = [];
        foreach ($class->getFieldNames() as $fieldName) {
            $classField = $class->getField($fieldName);
            assert($classField instanceof FieldMetadata);

            $type = $typeManager->getType($classField->type);
            $itemValue = $classField->getValue($value);

            if ($field->multiple) {
                $properties[$fieldName] = array_map(static function ($item) use ($type, $classField) {
                    return $type->toDatabase($item, $classField->options);
                }, (array) $itemValue);
            } elseif ($itemValue !== null) {
                $properties[$fieldName] = $type->toDatabase($itemValue, $classField->options);
            }
        }

        // @TODO: nested embedded documents.

        return $properties;
    }
}
