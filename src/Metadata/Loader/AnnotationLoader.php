<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Loader;

use Kcs\ClassFinder\Finder\FinderInterface;
use Kcs\ClassFinder\Finder\RecursiveFinder;
use Kcs\Metadata\Loader\AnnotationProcessorLoader;
use Refugis\ODM\Elastica\Annotation\Document;

class AnnotationLoader extends AnnotationProcessorLoader implements LoaderInterface
{
    use AnnotationLoaderTrait;

    protected function getFinder(): FinderInterface
    {
        $finder = new RecursiveFinder($this->prefixDir);
        $finder->annotatedBy(Document::class);

        return $finder;
    }
}
