<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Loader;

use Kcs\Metadata\Loader\LoaderInterface as BaseLoaderInterface;

interface LoaderInterface extends BaseLoaderInterface
{
    /**
     * @return class-string[]
     */
    public function getAllClassNames(): array;
}
