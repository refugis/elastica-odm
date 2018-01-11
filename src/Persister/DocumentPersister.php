<?php declare(strict_types=1);

namespace Fazland\ODM\Elastica\Persister;

use Elastica\Query;
use Fazland\ODM\Elastica\Collection\CollectionInterface;
use Fazland\ODM\Elastica\DocumentManagerInterface;
use Fazland\ODM\Elastica\Hydrator\HydratorInterface;
use Fazland\ODM\Elastica\Id\PostInsertId;
use Fazland\ODM\Elastica\Metadata\DocumentMetadata;
use Fazland\ODM\Elastica\Metadata\FieldMetadata;

class DocumentPersister
{
    /**
     * @var DocumentManagerInterface
     */
    private $dm;

    /**
     * @var DocumentMetadata
     */
    private $class;

    /**
     * @var CollectionInterface
     */
    private $collection;

    public function __construct(DocumentManagerInterface $dm, DocumentMetadata $class)
    {
        $this->dm = $dm;
        $this->class = $class;

        $this->collection = $dm->getCollection($class->name);
    }

    /**
     * @return DocumentMetadata
     */
    public function getClassMetadata(): DocumentMetadata
    {
        return $this->class;
    }

    /**
     * Finds a document by a set of criteria.
     *
     * @param array  $criteria query criteria
     * @param object $document The document to load data into. If not given, a new document will be created.
     *
     * @return object|null the loaded and managed document instance or null if no document was found
     */
    public function load(array $criteria, $document = null)
    {
        $query = $this->prepareQuery($criteria);
        $resultSet = $this->collection->search($query);

        if (! count($resultSet)) {
            return null;
        }

        $esDoc = $resultSet[0]->getDocument();

        if (null !== $document) {
            $this->dm->getUnitOfWork()->createDocument($esDoc, $document);

            return $document;
        }

        return $this->dm->newHydrator(HydratorInterface::HYDRATE_OBJECT)
            ->hydrateOne($esDoc, $this->class->name);
    }

    public function loadAll(array $criteria = [], array $orderBy = null, $limit = null, $offset = null): array
    {
        $query = $this->prepareQuery($criteria);
        $search = $this->collection->createSearch($this->dm, $query);
        $search->setSort($orderBy);

        if (null === $limit && null === $offset) {
            $search->setScroll(true);
        } else {
            if (null !== $limit) {
                $search->setLimit($limit);
            }

            if (null !== $offset) {
                $search->setOffset($offset);
            }
        }

        return $search->execute();
    }

    /**
     * Checks whether a document matching criteria exists in collection.
     *
     * @param array $criteria
     *
     * @return bool
     */
    public function exists(array $criteria): bool
    {
        $query = $this->prepareQuery($criteria);

        return $this->collection->count($query) > 0;
    }

    /**
     * Insert a document in the collection.
     *
     * @param object $document
     *
     * @return PostInsertId|null
     */
    public function insert($document): ?PostInsertId
    {
        /** @var DocumentMetadata $class */
        $class = $this->dm->getClassMetadata(get_class($document));
        $idGenerator = $this->dm->getUnitOfWork()->getIdGenerator($class->idGeneratorType);
        $postIdGenerator = $idGenerator->isPostInsertGenerator();

        $id = $postIdGenerator ? null : $class->getSingleIdentifier($document);
        $body = $this->prepareInsertData($class, $document);

        $response = $this->collection->create($id, $body);
        $data = $response->getData();

        foreach ($class->attributesMetadata as $field) {
            if (! $field instanceof FieldMetadata) {
                continue;
            }

            if ($field->indexName) {
                $field->setValue($document, $data['_index'] ?? null);
            }

            if ($field->typeName) {
                $field->setValue($document, $data['_type'] ?? null);
            }
        }

        $postInsertId = null;
        if ($postIdGenerator) {
            $postInsertId = new PostInsertId($document, $this->collection->getLastInsertedId());
        }

        return $postInsertId;
    }

    /**
     * Updates a managed document.
     *
     * @param object $document
     * @param array  $changeSet
     */
    public function update($document, array $changeSet): void
    {
        $class = $this->dm->getClassMetadata(get_class($document));
        $body = $this->prepareUpdateData($class, $changeSet);
        $id = $class->getSingleIdentifier($document);

        $this->collection->update((string) $id, $body);
    }

    /**
     * Deletes a managed document.
     *
     * @param object $document
     */
    public function delete($document): void
    {
        $class = $this->dm->getClassMetadata(get_class($document));
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

        $query = Query::create($bool);
        $query->setSource($this->class->eagerFieldNames);

        return $query;
    }

    private function prepareInsertData(DocumentMetadata $class, $document): array
    {
        $fields = [];
        foreach ($class->attributesMetadata as $field) {
            if (! $field instanceof FieldMetadata) {
                continue;
            }

            if ($field->identifier || $field->indexName || $field->typeName) {
                continue;
            }

            $fields[$field->name] = [null, $field->getValue($document)];
        }

        return $this->prepareUpdateData($class, $fields);
    }

    private function prepareUpdateData(DocumentMetadata $class, array $fields): array
    {
        $body = [];
        $typeManager = $this->dm->getTypeManager();

        foreach ($fields as $name => $value) {
            $field = $class->attributesMetadata[$name];

            $type = $typeManager->getType($field->type);
            $body[$field->fieldName] = $type->toDatabase($value[1]);
        }

        return $body;
    }
}
