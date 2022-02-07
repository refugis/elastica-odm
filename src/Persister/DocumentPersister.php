<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Persister;

use Elastica\Bulk;
use Elastica\Document;
use Elastica\Query;
use Elastica\Script\Script;
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
            if ($routing !== null) {
                $action->setRouting($routing);
            }

            $operations[] = $action;
        }

        try {
            $responseSet = $this->collection->bulk($operations)->getBulkResponses();
        } catch (IndexNotFoundException $e) {
            $schemaGenerator = new SchemaGenerator($this->dm);
            $schema = $schemaGenerator->generateSchema()->getMapping()[$this->class->name] ?? null;

            if ($schema === null) {
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
                }

                if (! $field->typeName) {
                    continue;
                }

                $field->setValue($document, $data['_type'] ?? null);
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

        try {
            $response = $this->collection->create($id, $body, $routing);
        } catch (IndexNotFoundException $e) {
            $schemaGenerator = new SchemaGenerator($this->dm);
            $schema = $schemaGenerator->generateSchema()->getMapping()[$this->class->name] ?? null;

            if ($schema === null) {
                throw $e;
            }

            $this->collection->updateMapping($schema->getMapping());
            $response = $this->collection->create($id, $body, $routing);
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
            if ($routing !== null) {
                $action->setRouting($routing);
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

        $joinFieldName = $class->join['fieldName'] ?? null;
        foreach ($changeSet as $name => $value) {
            if ($name === $joinFieldName) {
                $body[$joinFieldName] = $value[1];
                continue;
            }

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

    /**
     * @return array<string, mixed>
     *
     * @throws ConversionFailedException
     */
    private function prepareEmbeddedUpdateData(object $value, EmbeddedMetadata $field): array
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

            if ($classField->multiple) {
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

    private function getRouting(DocumentMetadata $class, object $document): ?string
    {
        if ($class->join === null) {
            return null;
        }

        $routingObject = $document;
        $metadata = $class;

        while ($metadata->parentField !== null) {
            $routingObject = $metadata->getField($metadata->parentField)->getValue($routingObject);
            $metadata = $this->dm->getClassMetadata(ClassUtil::getClass($routingObject));
        }

        return $metadata->getSingleIdentifier($routingObject);
    }
}
