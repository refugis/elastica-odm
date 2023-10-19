<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\DiscriminatorField;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

/** @Processor(annotation=DiscriminatorField::class) */
class DiscriminatorFieldProcessor implements ProcessorInterface
{
    /**
     * {@inheritDoc}
     *
     * @param DocumentMetadata $metadata
     * @param DiscriminatorField $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $metadata->discriminatorField = $subject->name;
    }
}
