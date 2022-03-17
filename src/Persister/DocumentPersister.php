<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Persister;

use ArrayObject;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Elastica\Bulk;
use Elastica\Document;
use Elastica\Query;
use Elastica\Script\Script;
use Refugis\ODM\Elastica\Annotation\Version;
use Refugis\ODM\Elastica\Collection\CollectionInterface;
use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Exception\ConversionFailedException;
use Refugis\ODM\Elastica\Exception\IndexNotFoundException;
use Refugis\ODM\Elastica\Exception\RuntimeException;
use Refugis\ODM\Elastica\Hydrator\HydratorInterface;
use Refugis\ODM\Elastica\Id\PostInsertId;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Metadata\EmbeddedMetadata;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;
use Refugis\ODM\Elastica\Tools\SchemaGenerator;
use Refugis\ODM\Elastica\Util\ClassUtil;

use function array_filter;
use function array_map;
use function array_values;
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

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string>|null $orderBy
     *
     * @return object[]
     */
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
     * Insert multiple documents.
     *
     * @param object[] $documents
     *
     * @return array<PostInsertId | null>
     */
    public function bulkInsert(array $documents): array
    {
        $operations = [];
        foreach ($documents as $document) {
            $class = $this->dm->getClassMetadata(ClassUtil::getClass($document));
            assert($class instanceof DocumentMetadata);

            $idGenerator = $this->dm->getUnitOfWork()->getIdGenerator($class->idGeneratorType);
            $postIdGenerator = $idGenerator->isPostInsertGenerator();

            $id = $postIdGenerator ? null : $class->getSingleIdentifier($document);
            $body = $this->prepareUpdateData($document)['body'];
            $routing = $this->getRouting($class, $document);

            $doc = new Document($id, $body);
            $action = new Bulk\Action\CreateDocument($doc);
            $metadata = [];
            if ($routing !== null) {
                $metadata['routing'] = $routing;
            }

            if ($class->versionType === Version::EXTERNAL || $class->versionType === Version::EXTERNAL_GTE) {
                $version = $class->getVersion($document);
                if ($version !== null) {
                    $metadata['version_type'] = $class->versionType;
                    $metadata['version'] = $version;
                    $action = new Bulk\Action\IndexDocument($doc);
                }
            }

            $action->setMetadata($metadata + $action->getMetadata());
            $operations[] = $action;
        }

        try {
            $responseSet = $this->collection->bulk($operations)->getBulkResponses();
        } catch (IndexNotFoundException $e) {
            $schemaGenerator = new SchemaGenerator($this->dm);
            try {
                $schema = $schemaGenerator->generateSchema()->getCollectionByClass($this->class->name);
            } catch (RuntimeException $_) {
                throw $e;
            }

            $this->collection->updateMapping($schema->getMapping());
            $responseSet = $this->collection->bulk($operations)->getBulkResponses();
        }

        $ids = [];
        foreach (array_values($documents) as $i => $document) {
            $response = $responseSet[$i] ?? null;
            $data = $response !== null ? $response->getData() : [];

            $class = $this->dm->getClassMetadata(ClassUtil::getClass($document));
            assert($class instanceof DocumentMetadata);
            foreach ($class->attributesMetadata as $field) {
                if (! $field instanceof FieldMetadata) {
                    continue;
                }

                if ($field->indexName) {
                    $field->setValue($document, $data['_index'] ?? null);
                } elseif ($field->typeName) {
                    $field->setValue($document, $data['_type'] ?? null);
                } elseif ($field->seqNo) {
                    $field->setValue($document, $data['_seq_no'] ?? null);
                } elseif ($field->primaryTerm) {
                    $field->setValue($document, $data['_primary_term'] ?? null);
                } elseif ($field->version) {
                    $field->setValue($document, $data['_version'] ?? null);
                }
            }

            $idGenerator = $this->dm->getUnitOfWork()->getIdGenerator($class->idGeneratorType);
            $postIdGenerator = $idGenerator->isPostInsertGenerator();

            $postInsertId = null;
            if ($postIdGenerator) {
                $postInsertId = new PostInsertId($document, $data['_id'] ?? '');
            }

            $ids[] = $postInsertId;
        }

        return $ids;
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
        $routing = $this->getRouting($class, $document);
        $index = $class->getIndexName($document);

        $options = ['routing' => $routing, 'index' => $index];
        if ($class->versionType === Version::EXTERNAL || $class->versionType === Version::EXTERNAL_GTE) {
            $version = $class->getVersion($document);
            if ($version !== null) {
                $options['version_type'] = $class->versionType;
                $options['version'] = $version;
            }
        }

        try {
            $response = $this->collection->create($id, $body, $options);
        } catch (IndexNotFoundException $e) {
            $schemaGenerator = new SchemaGenerator($this->dm);
            try {
                $schema = $schemaGenerator->generateSchema()->getCollectionByName($index ?? $class->collectionName);
            } catch (RuntimeException $_) {
                throw $e;
            }

            $this->collection->updateMapping($schema->getMapping());
            $response = $this->collection->create($id, $body, $options);
        }

        $data = $response->getData();

        foreach ($class->attributesMetadata as $field) {
            if (! $field instanceof FieldMetadata) {
                continue;
            }

            if ($field->indexName) {
                $field->setValue($document, $data['_index'] ?? null);
            } elseif ($field->typeName) {
                $field->setValue($document, $data['_type'] ?? null);
            } elseif ($field->seqNo) {
                $field->setValue($document, $data['_seq_no'] ?? null);
            } elseif ($field->primaryTerm) {
                $field->setValue($document, $data['_primary_term'] ?? null);
            } elseif ($field->version) {
                $field->setValue($document, $data['_version'] ?? null);
            }
        }

        $postInsertId = null;
        if ($postIdGenerator) {
            $postInsertId = new PostInsertId($document, $this->collection->getLastInsertedId());
        }

        return $postInsertId;
    }

    /**
     * Updates multiple documents.
     *
     * @param object[] $documents
     */
    public function bulkUpdate(array $documents): void
    {
        $operations = [];
        foreach ($documents as $document) {
            $class = $this->dm->getClassMetadata(ClassUtil::getClass($document));
            assert($class instanceof DocumentMetadata);

            $id = $class->getSingleIdentifier($document);
            $data = $this->prepareUpdateData($document);

            $routing = $this->getRouting($class, $document);
            $seqNo = $class->getSequenceNumber($document);
            $primaryTerm = $class->getPrimaryTerm($document);
            $index = $class->getIndexName($document);

            $body = array_filter([
                'doc' => $data['body'],
                'script' => $data['script'],
            ]);

            if (count($body) > 1) {
                $tmp = [$data['script']];
                $params = [];

                $i = 0;
                foreach ($body['doc'] as $idx => $value) {
                    $paramName = 'p_' . $idx . '_' . ++$i;
                    $tmp[] = 'ctx._source.' . $idx . ' = params.' . $paramName;
                    $params[$paramName] = $value;
                }

                $script = implode('; ', $tmp) . ';';
                $body = new Script($script, $params);
            } elseif (isset($body['doc'])) {
                $body = new Document($id, $body['doc']);
            } else {
                $body = new Script($data['script']);
            }

            $action = new Bulk\Action\UpdateDocument($body);
            $metadata = $action->getMetadata() + array_filter([
                'routing' => $routing,
                'if_seq_no' => $seqNo,
                'if_primary_term' => $primaryTerm,
                '_index' => $index,
            ], static fn ($value) => $value !== null);

            if ($metadata) {
                $action->setMetadata($metadata);
            }

            $operations[] = $action;
        }

        $this->collection->bulk($operations);
    }

    /**
     * Updates a managed document.
     */
    public function update(object $document): void
    {
        $class = $this->dm->getClassMetadata(ClassUtil::getClass($document));
        assert($class instanceof DocumentMetadata);

        $data = $this->prepareUpdateData($document);
        $id = $class->getSingleIdentifier($document);

        $routing = $this->getRouting($class, $document);
        $seqNo = $class->getSequenceNumber($document);
        $primaryTerm = $class->getPrimaryTerm($document);
        $index = $class->getIndexName($document);

        $this->collection->update((string) $id, $data['body'], $data['script'], [
            'routing' => $routing,
            'seq_no' => $seqNo,
            'primary_term' => $primaryTerm,
            'index' => $index,
        ]);
    }

    /**
     * Deletes a managed document.
     */
    public function delete(object $document): void
    {
        $class = $this->dm->getClassMetadata(ClassUtil::getClass($document));
        $id = $class->getSingleIdentifier($document);

        $options = array_filter([
            'index' => $class->getIndexName($document),
        ], static fn ($value) => $value !== null);

        $this->collection->delete((string) $id, $options);
    }

    /**
     * Refreshes the underlying collection.
     */
    public function refreshCollection(): void
    {
        $this->collection->refresh();
    }

    /**
     * @param array<string, mixed> $criteria
     */
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
        $typeManager = $this->dm->getTypeManager();

        $class = $this->dm->getClassMetadata(ClassUtil::getClass($document));
        assert($class instanceof DocumentMetadata);

        foreach ($changeSet as $name => $value) {
            if ($name === $class->joinField) {
                $body[$class->joinField] = $value[1];
                continue;
            }

            if ($name === $class->discriminatorField) {
                $body[$class->discriminatorField] = $value[1];
                continue;
            }

            $field = $class->getField($name);
            if ($field instanceof EmbeddedMetadata) {
                if ($field->multiple) {
                    $embeddedData = array_map(function ($item) use ($field) {
                        return $this->prepareEmbeddedUpdateData($item, $field);
                    }, (array) $value[1]);

                    $fieldBody = [];
                    foreach ($embeddedData as [$embeddedBody, $embeddedScript]) {
                        $fieldBody[] = $embeddedBody;
                        $script = [...$script, ...$embeddedScript];
                    }

                    $body[$field->fieldName] = $fieldBody;
                } else {
                    [$embeddedBody, $embeddedScript] = $this->prepareEmbeddedUpdateData($value[1], $field);
                    $body[$field->fieldName] = $embeddedBody;
                    $script = [...$script, ...$embeddedScript];
                }
            } elseif ($field instanceof FieldMetadata) {
                $type = $typeManager->getType($field->type);

                if ($field->multiple) {
                    if ($value[1] === null) {
                        $value[1] = new ArrayCollection();
                    }

                    if (is_array($value[1])) {
                        $value[1] = new ArrayCollection($value[1]);
                    }

                    $body[$field->fieldName] = $value[1]
                        ->map(static fn ($item) => $type->toDatabase($item, $field->options))
                        ->toArray();
                } elseif ($value[1] !== null) {
                    $body[$field->fieldName] = $type->toDatabase($value[1], $field->options);
                } else {
                    $script[] = 'ctx._source.remove(\'' . str_replace('\'', '\\\'', $field->fieldName) . '\')';
                }
            }
        }

        return [
            'body' => empty($body) ? new ArrayObject() : $body,
            'script' => implode('; ', $script),
        ];
    }

    /**
     * @return mixed[]
     *
     * @throws ConversionFailedException
     */
    private function prepareEmbeddedUpdateData(object $value, EmbeddedMetadata $field): array
    {
        $class = $this->dm->getClassMetadata($field->targetClass);
        assert($class instanceof DocumentMetadata);

        $typeManager = $this->dm->getTypeManager();
        $properties = [];
        $script = [];

        foreach ([...$class->getFieldNames(), ...$class->embeddedFieldNames] as $fieldName) {
            $classField = $class->getField($fieldName);
            assert($classField !== null);

            $itemValue = $classField->getValue($value);
            if ($classField instanceof EmbeddedMetadata) {
                if ($classField->multiple) {
                    assert($itemValue instanceof DoctrineCollection);
                    $embeddedData = $itemValue->map(function ($item) use ($classField) {
                        return $this->prepareEmbeddedUpdateData($item, $classField);
                    });

                    $fieldBody = [];
                    foreach ($embeddedData as [$embeddedBody, $embeddedScript]) {
                        $fieldBody[] = $embeddedBody;
                        $script = [...$script, ...$embeddedScript];
                    }

                    $properties[$classField->fieldName] = $fieldBody;
                } else {
                    [$embeddedBody, $embeddedScript] = $this->prepareEmbeddedUpdateData($itemValue, $classField);
                    $properties[$classField->fieldName] = $embeddedBody;
                    $script = [...$script, ...$embeddedScript];
                }
            } elseif ($classField instanceof FieldMetadata) {
                $type = $typeManager->getType($classField->type);

                if ($classField->multiple) {
                    assert($itemValue instanceof DoctrineCollection);
                    $properties[$fieldName] = $itemValue
                        ->map(static fn ($item) => $type->toDatabase($item, $classField->options))
                        ->toArray();
                } elseif ($itemValue !== null) {
                    $properties[$fieldName] = $type->toDatabase($itemValue, $classField->options);
                }
            }
        }

        return [$properties, $script];
    }

    private function getRouting(DocumentMetadata $class, object $document): ?string
    {
        if ($class->joinField === null) {
            return null;
        }

        $routingObject = $document;
        $metadata = $class;

        while ($metadata->getParentDocumentField() !== null) {
            $obj = $metadata->getParentDocument($routingObject);
            if ($obj === null) {
                break;
            }

            $routingObject = $obj;
            $metadata = $this->dm->getClassMetadata(ClassUtil::getClass($routingObject));
        }

        if ($routingObject === $document) {
            return null;
        }

        return $metadata->getSingleIdentifier($routingObject);
    }
}
