<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\Field;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;

/**
 * @Processor(annotation=Field::class)
 */
class FieldProcessor implements ProcessorInterface
{
    /**
     * {@inheritdoc}
     *
     * @param FieldMetadata $metadata
     * @param Field         $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $metadata->field = true;
        $metadata->fieldName = $subject->name ?? $metadata->name;
        $metadata->type = $subject->type;
        $metadata->multiple = $subject->multiple;
        $metadata->options = $subject->options;
        $metadata->lazy = $subject->lazy;
    }
}
