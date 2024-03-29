<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Repository;

use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;
use Refugis\ODM\Elastica\Search\Search;

/**
 * @template T of object
 * @extends ObjectRepository<T>
 * @extends Selectable<array-key, T>
 */
interface DocumentRepositoryInterface extends ObjectRepository, Selectable
{
    /**
     * Creates a Search object for the current class.
     */
    public function createSearch(): Search;
}
