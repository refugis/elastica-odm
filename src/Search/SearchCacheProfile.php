<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Search;

final class SearchCacheProfile
{
    private string $cacheKey;
    private int $ttl;

    public function __construct(string $cacheKey, int $ttl = 0)
    {
        $this->cacheKey = $cacheKey;
        $this->ttl = $ttl;
    }

    public function getCacheKey(): string
    {
        return $this->cacheKey;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }
}
