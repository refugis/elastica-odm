<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Repository;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Persister\DocumentPersister;
use Refugis\ODM\Elastica\Search\Search;
use Refugis\ODM\Elastica\UnitOfWork;

/**
 * @template T of object
 * @implements DocumentRepositoryInterface<T>
 */
class DocumentRepository implements DocumentRepositoryInterface
{
    protected DocumentManagerInterface $dm;
    protected DocumentMetadata $class;

    /** @phpstan-var class-string */
    protected string $documentClass;
    protected UnitOfWork $uow;

    public function __construct(DocumentManagerInterface $documentManager, DocumentMetadata $class)
    {
        $this->dm = $documentManager;
        $this->class = $class;
        $this->documentClass = $class->name;
        $this->uow = $documentManager->getUnitOfWork();
    }

    /**
     * {@inheritDoc}
     *
     * @return T|null
     */
    public function find($id): ?object
    {
        return $this->dm->find($this->documentClass, $id);
    }

    /**
     * {@inheritDoc}
     *
     * @return T[]
     */
    public function findAll(): array
    {
        return $this->findBy([]);
    }

    /**
     * {@inheritDoc}
     *
     * @return T[]
     */
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
    {
        return $this->getDocumentPersister()->loadAll($criteria, $orderBy, $limit, $offset);
    }

    /**
     * {@inheritDoc}
     *
     * @return T|null
     */
    public function findOneBy(array $criteria): ?object
    {
        return $this->getDocumentPersister()->load($criteria);
    }

    /** @return class-string<T> */
    public function getClassName(): string
    {
        return $this->documentClass;
    }

    /** @return Collection<T> */
    public function matching(Criteria $criteria): Collection
    {
        // TODO: Implement matching() method.
    }

    /** @return Search<T> */
    public function createSearch(): Search
    {
        return $this->dm->createSearch($this->documentClass);
    }

    /**
     * Gets the document persister for this document class.
     *
     * @return DocumentPersister<T>
     */
    protected function getDocumentPersister(): DocumentPersister
    {
        return $this->uow->getDocumentPersister($this->documentClass);
    }
}
