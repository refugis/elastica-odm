<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Loader;

use Kcs\Metadata\ClassMetadataInterface;

use function array_push;
use function array_unique;

class ChainLoader implements LoaderInterface
{
    /** @var LoaderInterface[] */
    private array $loaders;

    public function __construct(array $loaders)
    {
        $this->loaders = (static function (...$loaders) {
            return $loaders;
        })(...$loaders);
    }

    public function addLoader(LoaderInterface $loader): void
    {
        $this->loaders[] = $loader;
    }

    public function getAllClassNames(): array
    {
        $classes = [];
        foreach ($this->loaders as $loader) {
            array_push($classes, ...$loader->getAllClassNames());
        }

        return array_unique($classes);
    }

    public function loadClassMetadata(ClassMetadataInterface $classMetadata): bool
    {
        $success = false;

        foreach ($this->loaders as $loader) {
            $success = $loader->loadClassMetadata($classMetadata) || $success;
        }

        return $success;
    }
}
