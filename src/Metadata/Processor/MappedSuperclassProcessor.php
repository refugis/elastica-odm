<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\MappedSuperclass;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

/** @Processor(annotation=MappedSuperclass::class) */
class MappedSuperclassProcessor implements ProcessorInterface
{
    /**
     * {@inheritDoc}
     *
     * @param DocumentMetadata $metadata
     * @param MappedSuperclass $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $metadata->mappedSuperclass = true;
    }
}
