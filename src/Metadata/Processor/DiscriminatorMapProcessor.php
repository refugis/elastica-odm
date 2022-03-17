<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\DiscriminatorMap;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

/**
 * @Processor(annotation=DiscriminatorMap::class)
 */
class DiscriminatorMapProcessor implements ProcessorInterface
{
    /**
     * {@inheritdoc}
     *
     * @param DocumentMetadata $metadata
     * @param DiscriminatorMap $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $metadata->discriminatorMap = $subject->map;
        $metadata->discriminatorField ??= 'discr';
    }
}
