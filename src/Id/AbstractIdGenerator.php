<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Id;

abstract class AbstractIdGenerator implements GeneratorInterface
{
    public function isPostInsertGenerator(): bool
    {
        return false;
    }
}
