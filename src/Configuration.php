<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica;

use Kcs\Metadata\Factory\MetadataFactoryInterface;
use ProxyManager\Factory\LazyLoadingGhostFactory;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;
use Refugis\ODM\Elastica\Exception\InvalidDocumentRepositoryException;
use Refugis\ODM\Elastica\Repository\DefaultRepositoryFactory;
use Refugis\ODM\Elastica\Repository\DocumentRepository;
use Refugis\ODM\Elastica\Repository\DocumentRepositoryInterface;
use Refugis\ODM\Elastica\Repository\RepositoryFactoryInterface;
use Refugis\ODM\Elastica\Type\TypeManager;

final class Configuration
{
    /**
     * The document metadata factory.
     */
    private MetadataFactoryInterface $metadataFactory;

    /**
     * The document proxy factory.
     */
    private LazyLoadingGhostFactory $proxyFactory;

    /**
     * The result cache implementation.
     */
    private ?CacheItemPoolInterface $resultCache = null;

    /**
     * The type manager.
     */
    private TypeManager $typeManager;

    private ?RepositoryFactoryInterface $repositoryFactory = null;
    private ?string $defaultRepositoryClassName = null;

    public function __construct()
    {
        $this->typeManager = new TypeManager();
    }

    /**
     * Sets the document proxy factory.
     *
     * @required
     */
    public function setProxyFactory(LazyLoadingGhostFactory $proxyFactory): self
    {
        $this->proxyFactory = $proxyFactory;

        return $this;
    }

    /**
     * Sets the metadata factory.
     *
     * @required
     */
    public function setMetadataFactory(MetadataFactoryInterface $metadataFactory): self
    {
        $this->metadataFactory = $metadataFactory;

        return $this;
    }

    /**
     * Sets the result cache implementation.
     */
    public function setResultCache(?CacheItemPoolInterface $resultCache = null): self
    {
        $this->resultCache = $resultCache;

        return $this;
    }

    /**
     * Sets the type manager.
     */
    public function setTypeManager(TypeManager $typeManager): self
    {
        $this->typeManager = $typeManager;

        return $this;
    }

    /**
     * Sets the repository factory.
     */
    public function setRepositoryFactory(?RepositoryFactoryInterface $repositoryFactory): self
    {
        $this->repositoryFactory = $repositoryFactory;

        return $this;
    }

    /**
     * Sets default repository class.
     *
     * @phpstan-param class-string $className
     *
     * @throws InvalidDocumentRepositoryException
     */
    public function setDefaultRepositoryClassName(string $className): void
    {
        $reflectionClass = new ReflectionClass($className);
        if (! $reflectionClass->implementsInterface(DocumentRepositoryInterface::class)) {
            throw new InvalidDocumentRepositoryException($className);
        }

        $this->defaultRepositoryClassName = $className;
    }

    /**
     * Gets the document proxy factory.
     */
    public function getProxyFactory(): LazyLoadingGhostFactory
    {
        return $this->proxyFactory;
    }

    /**
     * Sets the metadata factory.
     */
    public function getMetadataFactory(): MetadataFactoryInterface
    {
        return $this->metadataFactory;
    }

    /**
     * Gets the result cache implementation.
     */
    public function getResultCache(): ?CacheItemPoolInterface
    {
        return $this->resultCache;
    }

    /**
     * Gets the type manager.
     */
    public function getTypeManager(): TypeManager
    {
        return $this->typeManager;
    }

    /**
     * Sets the repository factory.
     */
    public function getRepositoryFactory(): RepositoryFactoryInterface
    {
        if ($this->repositoryFactory !== null) {
            return $this->repositoryFactory;
        }

        $factory = new DefaultRepositoryFactory();
        $factory->setDefaultRepositoryClassName($this->getDefaultRepositoryClassName());

        return $factory;
    }

    /**
     * Get default repository class.
     */
    public function getDefaultRepositoryClassName(): string
    {
        return $this->defaultRepositoryClassName ?: DocumentRepository::class;
    }
}
