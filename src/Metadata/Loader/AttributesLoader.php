<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Loader;

use Kcs\ClassFinder\Finder\FinderInterface;
use Kcs\ClassFinder\Finder\RecursiveFinder;
use Kcs\Metadata\Loader\AttributesProcessorLoader;
use Refugis\ODM\Elastica\Annotation\Document;

class AttributesLoader extends AttributesProcessorLoader implements LoaderInterface
{
    use AnnotationLoaderTrait;

    protected function getFinder(): FinderInterface
    {
        $finder = new RecursiveFinder($this->prefixDir);
        $finder->withAttribute(Document::class);

        return $finder;
    }
}
