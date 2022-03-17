<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\JoinField;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

/**
 * @Processor(annotation=JoinField::class)
 */
class JoinFieldProcessor implements ProcessorInterface
{
    /**
     * {@inheritdoc}
     *
     * @param DocumentMetadata $metadata
     * @param JoinField $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $metadata->joinField = $subject->name;
    }
}
