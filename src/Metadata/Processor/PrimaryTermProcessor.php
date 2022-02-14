<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\PrimaryTerm;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;

/**
 * @Processor(annotation=PrimaryTerm::class)
 */
class PrimaryTermProcessor implements ProcessorInterface
{
    /**
     * {@inheritdoc}
     *
     * @param FieldMetadata $metadata
     * @param PrimaryTerm   $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $metadata->primaryTerm = true;
    }
}
