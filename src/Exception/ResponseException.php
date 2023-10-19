<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Exception;

use Elastica\Response;
use Throwable;

class ResponseException extends RuntimeException
{
    private Response $response;

    public function __construct(Response $response, string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->response = $response;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
