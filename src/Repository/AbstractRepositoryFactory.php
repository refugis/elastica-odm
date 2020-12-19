<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Repository;

use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

/**
 * Abstract factory for creating document repositories.
 */
abstract class AbstractRepositoryFactory implements RepositoryFactoryInterface
{
    private string $defaultRepositoryClassName;

    /**
     * The list of DocumentRepository instances.
     *
     * @var DocumentRepositoryInterface[]
     */
    private array $repositoryList = [];

    /**
     * {@inheritdoc}
     */
    public function getRepository(DocumentManagerInterface $documentManager, string $documentName): DocumentRepositoryInterface
    {
        $metadata = $documentManager->getClassMetadata($documentName);
        $hashKey = $metadata->getName().\spl_object_hash($documentManager);

        if (isset($this->repositoryList[$hashKey])) {
            return $this->repositoryList[$hashKey];
        }

        $repository = $this->createRepository($documentManager, \ltrim($documentName, '\\'));
        $this->repositoryList[$hashKey] = $repository;

        return $repository;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultRepositoryClassName(string $defaultRepositoryClassName): void
    {
        $this->defaultRepositoryClassName = $defaultRepositoryClassName;
    }

    /**
     * Create a new repository instance for a document class.
     */
    protected function createRepository(DocumentManagerInterface $documentManager, string $documentName): DocumentRepositoryInterface
    {
        $class = $documentManager->getClassMetadata($documentName);
        $repositoryClassName = $class->customRepositoryClassName ?: $this->defaultRepositoryClassName;

        return $this->instantiateRepository($repositoryClassName, $documentManager, $class);
    }

    /**
     * Instantiates requested repository.
     */
    abstract protected function instantiateRepository(
        string $repositoryClassName,
        DocumentManagerInterface $documentManager,
        DocumentMetadata $metadata
    ): DocumentRepositoryInterface;
}
