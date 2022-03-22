<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Events;

use Doctrine\Common\EventArgs;
use Doctrine\Persistence\ObjectManager;
use Elastica\Response;

class IndexNotFoundEventArgs extends EventArgs
{
    private ObjectManager $objectManager;
    private string $indexName;
    private ?Response $response;
    private bool $retry;

    public function __construct(ObjectManager $objectManager, string $indexName, ?Response $response)
    {
        $this->objectManager = $objectManager;
        $this->indexName = $indexName;
        $this->response = $response;
        $this->retry = false;
    }

    public function getObjectManager(): ObjectManager
    {
        return $this->objectManager;
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function markForRetry(): void
    {
        $this->retry = true;
    }

    public function shouldRetry(): bool
    {
        return $this->retry;
    }
}
