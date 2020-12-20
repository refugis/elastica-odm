<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Exception;

use Exception;
use Refugis\ODM\Elastica\Repository\DocumentRepositoryInterface;

class InvalidDocumentRepositoryException extends Exception implements ExceptionInterface
{
    public function __construct(string $className)
    {
        parent::__construct('Repository class "' . $className . '" is invalid. It must implement "' . DocumentRepositoryInterface::class . '".');
    }
}
