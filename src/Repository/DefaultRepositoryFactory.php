<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Repository;

use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

final class DefaultRepositoryFactory extends AbstractRepositoryFactory
{
    protected function instantiateRepository(
        string $repositoryClassName,
        DocumentManagerInterface $documentManager,
        DocumentMetadata $metadata
    ): DocumentRepositoryInterface {
        return new $repositoryClassName($documentManager, $metadata);
    }
}
