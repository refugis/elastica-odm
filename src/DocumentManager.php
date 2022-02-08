<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica;

use Doctrine\Common\EventManager;
use Elastica\Query;
use InvalidArgumentException;
use Kcs\Metadata\Factory\MetadataFactoryInterface;
use ProxyManager\Factory\LazyLoadingGhostFactory;
use ProxyManager\Proxy\LazyLoadingInterface;
use ProxyManager\Proxy\ProxyInterface;
use Psr\Cache\CacheItemPoolInterface;
use Refugis\ODM\Elastica\Collection\CollectionInterface;
use Refugis\ODM\Elastica\Collection\DatabaseInterface;
use Refugis\ODM\Elastica\Hydrator\HydratorInterface;
use Refugis\ODM\Elastica\Hydrator\Internal\ProxyInstantiator;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Persister\Hints;
use Refugis\ODM\Elastica\Repository\DocumentRepositoryInterface;
use Refugis\ODM\Elastica\Repository\RepositoryFactoryInterface;
use Refugis\ODM\Elastica\Search\Search;
use Refugis\ODM\Elastica\Type\TypeManager;
use Refugis\ODM\Elastica\Util\ClassUtil;

use function assert;
use function get_parent_class;
use function gettype;
use function is_object;
use function is_subclass_of;

class DocumentManager implements DocumentManagerInterface
{
    private DatabaseInterface $database;
    private MetadataFactoryInterface $metadataFactory;
    private LazyLoadingGhostFactory $proxyFactory;
    private TypeManager $typeManager;
    private UnitOfWork $unitOfWork;
    private EventManager $eventManager;
    private RepositoryFactoryInterface $repositoryFactory;
    private ?CacheItemPoolInterface $resultCache;

    public function __construct(DatabaseInterface $database, Configuration $configuration, ?EventManager $eventManager = null)
    {
        $this->database = $database;
        $this->eventManager = $eventManager ?: new EventManager();

        $this->metadataFactory = $configuration->getMetadataFactory();
        $this->proxyFactory = $configuration->getProxyFactory();
        $this->typeManager = $configuration->getTypeManager();
        $this->unitOfWork = new UnitOfWork($this);
        $this->repositoryFactory = $configuration->getRepositoryFactory();
        $this->resultCache = $configuration->getResultCache();

        $this->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function find($className, $id): ?object
    {
        $class = $this->getClassMetadata($className);
        if ($document = $this->unitOfWork->tryGetById($id, $class)) {
            return $document;
        }

        $persister = $this->getUnitOfWork()->getDocumentPersister($className);

        return $persister->load(['_id' => $id]);
    }

    /**
     * {@inheritdoc}
     */
    public function getReference(string $className, $id): object
    {
        $class = $this->getClassMetadata($className);
        if ($document = $this->unitOfWork->tryGetById($id, $class)) {
            return $document;
        }

        $instantiator = new ProxyInstantiator([$class->identifier->name], $this);

        $document = $instantiator->instantiate($className);
        $class->identifier->setValue($document, $id);

        $this->unitOfWork->registerManaged($document, []);

        return $document;
    }

    /**
     * {@inheritdoc}
     */
    public function persist($object): void
    {
        if (! is_object($object)) {
            throw new InvalidArgumentException('Expected object, ' . gettype($object) . ' given.');
        }

        $this->unitOfWork->persist($object);
    }

    /**
     * {@inheritdoc}
     */
    public function remove($object): void
    {
        if (! is_object($object)) {
            throw new InvalidArgumentException('Expected object, ' . gettype($object) . ' given.');
        }

        $this->unitOfWork->remove($object);
    }

    /**
     * {@inheritdoc}
     */
    public function merge($object): object
    {
        if (! is_object($object)) {
            throw new InvalidArgumentException('Expected object, ' . gettype($object) . ' given.');
        }

        return $this->unitOfWork->merge($object);
    }

    /**
     * {@inheritdoc}
     */
    public function clear($objectName = null): void
    {
        $this->unitOfWork->clear($objectName);
    }

    /**
     * {@inheritdoc}
     */
    public function detach($object): void
    {
        if (! is_object($object)) {
            throw new InvalidArgumentException('Expected object, ' . gettype($object) . ' given.');
        }

        $this->unitOfWork->detach($object);
    }

    /**
     * {@inheritdoc}
     */
    public function refresh($object): void
    {
        $class = $this->getClassMetadata(ClassUtil::getClass($object));
        $persister = $this->unitOfWork->getDocumentPersister($class->name);

        $persister->load(['_id' => $class->getSingleIdentifier($object)], [Hints::HINT_REFRESH => true], $object);
    }

    public function flush(): void
    {
        $this->unitOfWork->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository($className): DocumentRepositoryInterface
    {
        return $this->repositoryFactory->getRepository($this, $className);
    }

    /**
     * {@inheritdoc}
     */
    public function getClassMetadata($className): DocumentMetadata
    {
        if (is_subclass_of($className, ProxyInterface::class)) {
            $className = get_parent_class($className);
        }

        $metadata = $this->metadataFactory->getMetadataFor($className);
        assert($metadata instanceof DocumentMetadata);

        return $metadata;
    }

    public function getMetadataFactory(): MetadataFactoryInterface
    {
        return $this->metadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function initializeObject($obj): void
    {
        if (! ($obj instanceof LazyLoadingInterface)) {
            return;
        }

        $obj->initializeProxy();
    }

    /**
     * {@inheritdoc}
     */
    public function contains($object): bool
    {
        if (! is_object($object)) {
            throw new InvalidArgumentException('Expected object, ' . gettype($object) . ' given.');
        }

        return $this->unitOfWork->isInIdentityMap($object);
    }

    public function getProxyFactory(): LazyLoadingGhostFactory
    {
        return $this->proxyFactory;
    }

    public function getDatabase(): DatabaseInterface
    {
        return $this->database;
    }

    public function getEventManager(): EventManager
    {
        return $this->eventManager;
    }

    public function getUnitOfWork(): UnitOfWork
    {
        return $this->unitOfWork;
    }

    public function getTypeManager(): TypeManager
    {
        return $this->typeManager;
    }

    public function getCollection(string $className): CollectionInterface
    {
        $class = $this->getClassMetadata($className);

        return $this->database->getCollection($class);
    }

    public function getResultCache(): ?CacheItemPoolInterface
    {
        return $this->resultCache;
    }

    public function newHydrator(int $hydrationMode): HydratorInterface
    {
        switch ($hydrationMode) {
            case HydratorInterface::HYDRATE_OBJECT:
                return new Hydrator\ObjectHydrator($this);
        }

        throw new InvalidArgumentException('Invalid hydration mode ' . $hydrationMode);
    }

    public function createSearch(string $className): Search
    {
        $collection = $this->getCollection($className);

        return $collection->createSearch($this, Query::create(''));
    }
}
