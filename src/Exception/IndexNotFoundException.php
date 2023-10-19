<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Exception;

use Elastica\Response;
use Throwable;

class IndexNotFoundException extends ResponseException
{
    private string $indexName;

    public function __construct(Response $response, string $indexName, string $message, ?Throwable $previous = null)
    {
        parent::__construct($response, $message, 0, $previous);

        $this->indexName = $indexName;
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }
}
