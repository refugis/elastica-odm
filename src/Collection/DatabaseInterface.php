<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Collection;

use Elastica\Client;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

interface DatabaseInterface
{
    /**
     * Gets the elastic search connection.
     *
     * @return Client
     */
    public function getConnection(): Client;

    /**
     * Retrieve a collection from class metadata.
     *
     * @param DocumentMetadata $class
     *
     * @return CollectionInterface
     */
    public function getCollection(DocumentMetadata $class): CollectionInterface;
}
