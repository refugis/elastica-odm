<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Platform;

class ES6Platform implements PlatformInterface
{
    public function supportsRoutingKeyUpdate(): bool
    {
        return false;
    }
}
