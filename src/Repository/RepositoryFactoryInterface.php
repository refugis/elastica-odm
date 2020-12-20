<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Repository;

use Refugis\ODM\Elastica\DocumentManagerInterface;

interface RepositoryFactoryInterface
{
    /**
     * Gets the repository for a document class.
     */
    public function getRepository(DocumentManagerInterface $documentManager, string $documentName): DocumentRepositoryInterface;

    /**
     * Sets the default repository fully-qualified class name.
     */
    public function setDefaultRepositoryClassName(string $defaultRepositoryClassName): void;
}
