<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Exception;

use RuntimeException;
use Throwable;

use function Safe\sprintf;

class CannotDropAnAliasException extends RuntimeException
{
    public function __construct(string $indexName, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('"%s" is an alias and cannot be dropped.', $indexName), 0, $previous);
    }
}
