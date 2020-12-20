<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Collection;

use Elastica\Client;
use Elastica\SearchableInterface;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

use function assert;
use function explode;

class Database implements DatabaseInterface
{
    protected Client $elasticSearch;

    /** @var CollectionInterface[] */
    private array $collectionList;

    public function __construct(Client $elasticSearch)
    {
        $this->elasticSearch = $elasticSearch;
        $this->collectionList = [];
    }

    public function getConnection(): Client
    {
        return $this->elasticSearch;
    }

    public function getCollection(DocumentMetadata $class): CollectionInterface
    {
        if (isset($this->collectionList[$class->name])) {
            return $this->collectionList[$class->name];
        }

        $collection = new Collection($class->name, $this->getSearchable($class));
        $collection->setStaticSettings($class->staticSettings ?? []);
        $collection->setDynamicSettings($class->dynamicSettings ?? []);

        return $this->collectionList[$class->name] = $collection;
    }

    protected function getSearchable(DocumentMetadata $class): SearchableInterface
    {
        [$indexName, $typeName] = explode('/', $class->collectionName, 2) + [null, null];
        assert($indexName !== null);

        $searchable = $this->elasticSearch->getIndex($indexName);
        if ($typeName !== null) {
            $searchable = $searchable->getType($typeName);
        }

        return $searchable;
    }
}
