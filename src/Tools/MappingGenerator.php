<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tools;

use Elastica\Mapping;
use Elastica\Type\Mapping as TypeMapping;
use Kcs\Metadata\Factory\MetadataFactoryInterface;
use Refugis\ODM\Elastica\Exception\RuntimeException;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Metadata\EmbeddedMetadata;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;
use Refugis\ODM\Elastica\Type\TypeManager;

use function assert;
use function class_exists;
use function sprintf;

final class MappingGenerator
{
    private TypeManager $typeManager;
    private MetadataFactoryInterface $metadataFactory;

    public function __construct(TypeManager $typeManager, MetadataFactoryInterface $metadataFactory)
    {
        $this->typeManager = $typeManager;
        $this->metadataFactory = $metadataFactory;
    }

    public function getMapping(DocumentMetadata $class): object
    {
        $properties = $this->generatePropertiesMapping($class);

        return class_exists(TypeMapping::class) ? TypeMapping::create($properties) : Mapping::create($properties);
    }

    /**
     * @return array<string, mixed>
     */
    private function generatePropertiesMapping(DocumentMetadata $class): array
    {
        $properties = [];

        foreach ($class->getAttributesMetadata() as $field) {
            if ($field instanceof EmbeddedMetadata) {
                $embeddedClass = $this->metadataFactory->getMetadataFor($field->targetClass);
                if ($field->enabled) {
                    $mapping = ['type' => 'nested'];
                } else {
                    $mapping = [
                        'type' => 'object',
                        'enabled' => false,
                    ];
                }

                $mapping += [
                    'dynamic' => 'strict',
                    'properties' => $this->generatePropertiesMapping($embeddedClass),
                ];

                $properties[$field->fieldName] = $mapping;
            }

            if (! $field instanceof FieldMetadata || ! $field->isStored()) {
                continue;
            }

            $type = $this->typeManager->getType($field->type);
            $mapping = $type->getMappingDeclaration($field->options);
            if (isset($field->options['index'])) {
                $mapping['index'] = $field->options['index'];
            }

            if (! empty($field->options['fields'])) {
                $mapping['fields'] = $field->options['fields'];
            }

            $properties[$field->fieldName] = $mapping;
        }

        if (isset($class->join['type']) && ! isset($class->join['parentClass'])) {
            $properties[$class->join['fieldName']] = [
                'type' => 'join',
                'relations' => $class->join['relations'],
            ];
        }

        foreach ($class->childrenClasses as $childClassName) {
            $childClass = $this->metadataFactory->getMetadataFor($childClassName);
            assert($childClass instanceof DocumentMetadata);

            $childProperties = $this->generatePropertiesMapping($childClass);
            foreach ($childProperties as $name => $childProperty) {
                if (isset($properties[$name]) && $properties[$name] !== $childProperty) {
                    throw new RuntimeException(sprintf('Conflicting property definition while generating mapping for class "%s" and its children: definitions of field "%s" are incompatible.', $class->name, $name));
                }

                $properties[$name] = $childProperty;
            }
        }

        return $properties;
    }
}
