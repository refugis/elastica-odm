<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Loader;

use Kcs\ClassFinder\Finder\FinderInterface;
use Kcs\Metadata\ClassMetadataInterface;
use Kcs\Metadata\Loader\Processor\ProcessorFactoryInterface;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;
use TypeError;

use function get_debug_type;
use function Safe\sprintf;

trait AnnotationLoaderTrait
{
    private string $prefixDir;

    public function __construct(ProcessorFactoryInterface $processorFactory, string $prefixDir)
    {
        $this->prefixDir = $prefixDir;

        parent::__construct($processorFactory);
    }

    public function loadClassMetadata(ClassMetadataInterface $classMetadata): bool
    {
        if (! $classMetadata instanceof DocumentMetadata) {
            throw new TypeError(sprintf('Argument #1 passed to %s must be an instance of %s, %s given', __METHOD__, DocumentMetadata::class, get_debug_type($classMetadata)));
        }

        $reflectionClass = $classMetadata->getReflectionClass();
        $this->processClassDescriptors($classMetadata, $this->getClassDescriptors($reflectionClass));

        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $attributeMetadata = $this->createMethodMetadata($reflectionMethod);
            $this->processMethodDescriptors($attributeMetadata, $this->getMethodDescriptors($reflectionMethod));

            $classMetadata->addAttributeMetadata($attributeMetadata);
        }

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $attributeMetadata = new FieldMetadata($classMetadata, $reflectionProperty->name);
            $this->processPropertyDescriptors($attributeMetadata, $this->getPropertyDescriptors($reflectionProperty));

            $classMetadata->addAttributeMetadata($attributeMetadata);
        }

        return true;
    }

    public function getAllClassNames(): array
    {
        $classes = [];
        foreach ($this->getFinder() as $className => $reflection) {
            if (! $reflection->isInstantiable()) {
                continue;
            }

            $classes[] = $className;
        }

        return $classes;
    }

    abstract protected function getFinder(): FinderInterface;
}
