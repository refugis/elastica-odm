<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Search;

use Elastica\Query;
use Elastica\ResultSet;
use Generator;
use Iterator;
use IteratorAggregate;
use Psr\Cache\CacheItemPoolInterface;
use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Hydrator\HydratorInterface;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

use function assert;
use function is_array;
use function iterator_to_array;

class Search implements IteratorAggregate
{
    /**
     * The target document class.
     */
    private string $documentClass;

    /**
     * Hydration mode.
     */
    private int $hydrationMode;

    /**
     * Search query.
     */
    private Query $query;

    /**
     * Result cache profile.
     */
    private ?SearchCacheProfile $cacheProfile = null;

    /**
     * Whether to execute a scroll search or not.
     */
    private bool $scroll;

    /**
     * Sort fields.
     *
     * @var string[]
     */
    private ?array $sort = null;

    /**
     * Max returned results.
     */
    private ?int $limit = null;

    /**
     * Skipped documents.
     */
    private ?int $offset = null;

    /**
     * The document manager which this search is bound.
     */
    private DocumentManagerInterface $documentManager;

    public function __construct(DocumentManagerInterface $documentManager, string $documentClass)
    {
        $this->documentManager = $documentManager;
        $this->documentClass = $documentClass;
        $this->hydrationMode = HydratorInterface::HYDRATE_OBJECT;
        $this->scroll = false;

        $this->setQuery('');
    }

    /**
     * Gets the current document manager.
     */
    public function getDocumentManager(): DocumentManagerInterface
    {
        return $this->documentManager;
    }

    /**
     * Gets the document class to retrieve.
     */
    public function getDocumentClass(): string
    {
        return $this->documentClass;
    }

    /**
     * Gets the query results.
     *
     * @return object[]
     */
    public function execute(): array
    {
        return iterator_to_array($this->getIterator(), false);
    }

    /**
     * Get the total hits of the current query.
     */
    public function count(): int
    {
        $collection = $this->documentManager->getCollection($this->documentClass);

        return $collection->count($this->query);
    }

    /**
     * Iterate over the query results.
     */
    public function getIterator(): Iterator
    {
        $hydrator = $this->documentManager->newHydrator($this->hydrationMode);
        $query = clone $this->query;

        if (! $query->hasParam('_source')) {
            $class = $this->documentManager->getClassMetadata($this->documentClass);
            assert($class instanceof DocumentMetadata);
            $query->setSource($class->eagerFieldNames);
        }

        if ($this->sort !== null) {
            $query->setSort($this->sort);
        }

        if ($this->limit !== null) {
            $query->setSize($this->limit);
        }

        if ($this->offset !== null) {
            $query->setFrom($this->offset);
        }

        $generator = $this->cacheProfile !== null ? $this->_doExecuteCached($query) : $this->_doExecute($query);
        foreach ($generator as $resultSet) {
            yield from $hydrator->hydrateAll($resultSet, $this->documentClass);
        }
    }

    /**
     * Sets the search query.
     *
     * @param Query|string $query
     */
    public function setQuery($query): self
    {
        $this->query = Query::create($query);

        return $this;
    }

    /**
     * Gets the search query.
     */
    public function getQuery(): Query
    {
        return $this->query;
    }

    /**
     * Sets the sort fields and directions.
     *
     * @param array|string|null $fieldName
     */
    public function setSort($fieldName, string $order = 'asc'): self
    {
        if ($fieldName !== null) {
            $sort = [];
            $fields = is_array($fieldName) ? $fieldName : [$fieldName => $order];

            foreach ($fields as $fieldName => $order) {
                $sort[] = [$fieldName => $order];
            }
        } else {
            $sort = null;
        }

        $this->sort = $sort;

        return $this;
    }

    /**
     * Gets the sort array.
     */
    public function getSort(): ?array
    {
        return $this->sort;
    }

    /**
     * Sets the query limit.
     */
    public function setLimit(?int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Gets the max returned documents.
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * Sets the query first result.
     */
    public function setOffset(?int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Gets the query first result.
     */
    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function setScroll(bool $scroll = true): self
    {
        $this->scroll = $scroll;

        return $this;
    }

    public function isScroll(): bool
    {
        return $this->limit === null && $this->offset === null && $this->scroll;
    }

    /**
     * Instructs the executor to use a result cache.
     */
    public function useResultCache(?string $cacheKey = null, int $ttl = 0): self
    {
        if ($cacheKey === null) {
            $this->cacheProfile = null;
        } else {
            $this->cacheProfile = new SearchCacheProfile($cacheKey, $ttl);
        }

        return $this;
    }

    /**
     * Gets the cache profile (if set).
     */
    public function getCacheProfile(): ?SearchCacheProfile
    {
        return $this->cacheProfile;
    }

    /**
     * Executes the search action, yield all the result sets.
     *
     * @return Generator<ResultSet>
     */
    private function _doExecute(Query $query)
    {
        $collection = $this->documentManager->getCollection($this->documentClass);

        if ($this->isScroll()) {
            $scroll = $collection->scroll($query);

            foreach ($scroll as $resultSet) {
                yield $resultSet;
            }
        } else {
            yield $collection->search($query);
        }
    }

    /**
     * Executes a cached query.
     *
     * @return Generator<ResultSet>
     */
    private function _doExecuteCached(Query $query)
    {
        $resultCache = $this->documentManager->getResultCache();
        if ($resultCache !== null) {
            $item = $resultCache->getItem($this->cacheProfile->getCacheKey());

            if ($item->isHit()) {
                yield from $item->get();

                return;
            }
        }

        $resultSets = iterator_to_array($this->_doExecute($query));

        if (isset($item)) {
            assert($resultCache instanceof CacheItemPoolInterface);

            $item->set($resultSets);
            $item->expiresAfter($this->cacheProfile->getTtl());
            $resultCache->save($item);
        }

        yield from $resultSets;
    }
}
