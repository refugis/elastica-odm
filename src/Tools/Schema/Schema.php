<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tools\Schema;

/**
 * Holds the informations about the schema of collections.
 */
class Schema
{
    /**
     * @var array<string, Collection>
     */
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
}
