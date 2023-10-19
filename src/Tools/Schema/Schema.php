<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tools\Schema;

use Refugis\ODM\Elastica\Exception\RuntimeException;

use function sprintf;

/**
 * Holds information about the schema of collections.
 */
class Schema
{
    /** @var array<string, Collection> */
    private array $collectionMapping = [];

    /**
     * Adds a collection to the schema.
     */
    public function addCollection(Collection $collection): void
    {
        $metadata = $collection->getDocumentMetadata();
        $this->collectionMapping[$metadata->getName()] = $collection;
    }

    /**
     * Gets the collection mappings.
     *
     * @return array<string, Collection>
     */
    public function getMapping(): array
    {
        return $this->collectionMapping;
    }

    /**
     * Gets the collection mappings by class name.
     */
    public function getCollectionByClass(string $className): Collection
    {
        if (! isset($this->collectionMapping[$className])) {
            throw new RuntimeException(sprintf('Mapping for class "%s" does not exist', $className));
        }

        return $this->collectionMapping[$className];
    }

    /**
     * Gets the collection mappings by class name.
     */
    public function getCollectionByName(string $collectionName): Collection
    {
        foreach ($this->collectionMapping as $collection) {
            if ($collection->getDocumentMetadata()->collectionName !== $collectionName) {
                continue;
            }

            return $collection;
        }

        throw new RuntimeException(sprintf('Mapping for "%s" does not exist', $collectionName));
    }
}
