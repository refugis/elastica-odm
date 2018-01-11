<?php declare(strict_types=1);

namespace Fazland\ODM\Elastica;

use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\ObjectManager;
use Fazland\ODM\Elastica\Collection\CollectionInterface;
use Fazland\ODM\Elastica\Collection\DatabaseInterface;
use Fazland\ODM\Elastica\Hydrator\HydratorInterface;
use Fazland\ODM\Elastica\Type\TypeManager;
use ProxyManager\Factory\LazyLoadingGhostFactory;
use Psr\Cache\CacheItemPoolInterface;

interface DocumentManagerInterface extends ObjectManager
{
    /**
     * Returns the proxy factory used by this document manager.
     * See ocramius/proxy-manager for more info on how to use it.
     *
     * @return LazyLoadingGhostFactory
     */
    public function getProxyFactory(): LazyLoadingGhostFactory;

    /**
     * Returns the underlying database object.
     *
     * @return DatabaseInterface
     */
    public function getDatabase(): DatabaseInterface;

    /**
     * Gets the type manager used in this manager.
     * It must be used to register types converters.
     *
     * @return TypeManager
     */
    public function getTypeManager(): TypeManager;

    /**
     * Gets the current unit of work.
     * Holds currently active (attached) documents.
     *
     * @return UnitOfWork
     */
    public function getUnitOfWork(): UnitOfWork;

    /**
     * Gets the event manager used by this document manager.
     *
     * @return EventManager
     */
    public function getEventManager(): EventManager;

    /**
     * Gets the document collection for a given object class.
     *
     * @param string $className
     *
     * @return CollectionInterface
     */
    public function getCollection(string $className): CollectionInterface;

    /**
     * Retrieve the result cache pool, if configured.
     *
     * @return null|CacheItemPoolInterface
     */
    public function getResultCache(): ?CacheItemPoolInterface;

    /**
     * Gets an hydrator for the given hydration mode.
     *
     * @param int $hydrationMode
     *
     * @return HydratorInterface
     */
    public function newHydrator(int $hydrationMode): HydratorInterface;
}
