<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Platform;

class Platform implements PlatformInterface
{
    public function supportsRoutingKeyUpdate(): bool
    {
        return true;
    }
}
