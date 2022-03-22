<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Platform;

interface PlatformInterface
{
    /**
     * Whether ES supports routing parameter on "update" operation
     * even if routing key has been updated.
     */
    public function supportsRoutingKeyUpdate(): bool;
}
