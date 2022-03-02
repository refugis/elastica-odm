<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Loader;

use Kcs\ClassFinder\Finder\FinderInterface;
use Kcs\Metadata\ClassMetadataInterface;
use Kcs\Metadata\Loader\Processor\ProcessorFactory;
use Kcs\Metadata\Loader\Processor\ProcessorFactoryInterface;
use Refugis\ODM\Elastica\Annotation;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Metadata\EmbeddedMetadata;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;
use Refugis\ODM\Elastica\Metadata\Processor;
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

    public static function createProcessorFactory(): ProcessorFactoryInterface
    {
        $factory = new ProcessorFactory();
        $factory->registerProcessor(Annotation\DocumentId::class, Processor\DocumentIdProcessor::class);
        $factory->registerProcessor(Annotation\Document::class, Processor\DocumentProcessor::class);
        $factory->registerProcessor(Annotation\Embeddable::class, Processor\EmbeddableProcessor::class);
        $factory->registerProcessor(Annotation\Embedded::class, Processor\EmbeddedProcessor::class);
        $factory->registerProcessor(Annotation\Field::class, Processor\FieldProcessor::class);
        $factory->registerProcessor(Annotation\IndexName::class, Processor\IndexNameProcessor::class);
        $factory->registerProcessor(Annotation\Index::class, Processor\IndexProcessor::class);
        $factory->registerProcessor(Annotation\ParentDocument::class, Processor\ParentDocumentProcessor::class);
        $factory->registerProcessor(Annotation\PrimaryTerm::class, Processor\PrimaryTermProcessor::class);
        $factory->registerProcessor(Annotation\SequenceNumber::class, Processor\SequenceNumberProcessor::class);
        $factory->registerProcessor(Annotation\Setting::class, Processor\SettingProcessor::class);
        $factory->registerProcessor(Annotation\TypeName::class, Processor\TypeNameProcessor::class);

        return $factory;
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
            $descriptors = $this->getPropertyDescriptors($reflectionProperty);
            $embedded = false;
            foreach ($descriptors as $descriptor) {
                if (! ($descriptor instanceof Annotation\Embedded)) {
                    continue;
                }

                $embedded = true;
            }

            if ($embedded) {
                $attributeMetadata = new EmbeddedMetadata($classMetadata, $reflectionProperty->name);
            } else {
                $attributeMetadata = new FieldMetadata($classMetadata, $reflectionProperty->name);
            }

            $this->processPropertyDescriptors($attributeMetadata, $descriptors);
            $classMetadata->addAttributeMetadata($attributeMetadata);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
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
