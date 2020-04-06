<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tools;

use Elastica\Type\Mapping;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;
use Refugis\ODM\Elastica\Type\TypeManager;

final class MappingGenerator
{
    /**
     * @var TypeManager
     */
    private $typeManager;

    public function __construct(TypeManager $typeManager)
    {
        $this->typeManager = $typeManager;
    }

    public function getMapping(DocumentMetadata $class): Mapping
    {
        $properties = [];

        foreach ($class->getAttributesMetadata() as $field) {
            if (! $field instanceof FieldMetadata) {
                continue;
            }

            if (null === $field->type) {
                continue;
            }

            $type = $this->typeManager->getType($field->type);

            $mapping = $type->getMappingDeclaration($field->options);
            if (isset($field->options['index'])) {
                $mapping['index'] = $field->options['index'];
            }

            $properties[$field->fieldName] = $mapping;
        }

        return Mapping::create($properties);
    }
}
