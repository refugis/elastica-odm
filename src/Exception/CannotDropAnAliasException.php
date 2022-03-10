<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Exception;

use Elastica\Response;
use Throwable;

use function Safe\sprintf;

class CannotDropAnAliasException extends ResponseException
{
    public function __construct(Response $response, string $indexName, ?Throwable $previous = null)
    {
        parent::__construct($response, sprintf('"%s" is an alias and cannot be dropped.', $indexName), 0, $previous);
    }
}
