<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\JoinRelationsMap;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

/** @Processor(annotation=JoinRelationsMap::class) */
class JoinRelationsMapProcessor implements ProcessorInterface
{
    /**
     * {@inheritDoc}
     *
     * @param DocumentMetadata $metadata
     * @param JoinRelationsMap $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $metadata->joinRelationMap = $subject->map;
    }
}
