<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\Embeddable;
use Refugis\ODM\Elastica\Annotation\Embedded;
use Refugis\ODM\Elastica\Metadata\EmbeddedMetadata;

/**
 * @Processor(annotation=Embeddable::class)
 */
class EmbeddedProcessor implements ProcessorInterface
{
    /**
     * {@inheritdoc}
     *
     * @param EmbeddedMetadata $metadata
     * @param Embedded         $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $metadata->targetClass = $subject->targetClass;
        $metadata->enabled = $subject->enabled;
        $metadata->fieldName = $subject->name ?? $metadata->name;
        $metadata->multiple = $subject->multiple;
    }
}
