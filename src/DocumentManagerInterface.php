<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica;

use Doctrine\Common\EventManager;
use Doctrine\Persistence\ObjectManager;
use ProxyManager\Factory\LazyLoadingGhostFactory;
use Psr\Cache\CacheItemPoolInterface;
use Refugis\ODM\Elastica\Collection\CollectionInterface;
use Refugis\ODM\Elastica\Collection\DatabaseInterface;
use Refugis\ODM\Elastica\Hydrator\HydratorInterface;
use Refugis\ODM\Elastica\Search\Search;
use Refugis\ODM\Elastica\Type\TypeManager;

interface DocumentManagerInterface extends ObjectManager
{
    /**
     * Gets a reference to the document identified by the given type and identifier
     * without actually loading it, if the document is not yet loaded.
     *
     * @param mixed  $id
     */
    public function getReference(string $className, $id): object;

    /**
     * Returns the proxy factory used by this document manager.
     * See ocramius/proxy-manager for more info on how to use it.
     */
    public function getProxyFactory(): LazyLoadingGhostFactory;

    /**
     * Returns the underlying database object.
     */
    public function getDatabase(): DatabaseInterface;

    /**
     * Gets the type manager used in this manager.
     * It must be used to register types converters.
     */
    public function getTypeManager(): TypeManager;

    /**
     * Gets the current unit of work.
     * Holds currently active (attached) documents.
     */
    public function getUnitOfWork(): UnitOfWork;

    /**
     * Gets the event manager used by this document manager.
     */
    public function getEventManager(): EventManager;

    /**
     * Gets the document collection for a given object class.
     */
    public function getCollection(string $className): CollectionInterface;

    /**
     * Retrieve the result cache pool, if configured.
     */
    public function getResultCache(): ?CacheItemPoolInterface;

    /**
     * Gets an hydrator for the given hydration mode.
     */
    public function newHydrator(int $hydrationMode): HydratorInterface;

    /**
     * Creates a search object for the given class.
     */
    public function createSearch(string $className): Search;
}
