<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Hydrator;

use Elastica\Document;
use Elastica\ResultSet;

interface HydratorInterface
{
    public const HYDRATE_OBJECT = 1;
    public const HYDRATE_ARRAY = 2;

    /**
     * Hydrates all the documents in the result set.
     *
     * @return object[]
     */
    public function hydrateAll(ResultSet $resultSet, string $className): array;

    /**
     * Hydrates only one document.
     *
     * @return mixed
     */
    public function hydrateOne(Document $document, string $className);
}
