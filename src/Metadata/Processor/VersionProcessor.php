<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\Version;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;

/**
 * @Processor(annotation=Version::class)
 */
class VersionProcessor implements ProcessorInterface
{
    /**
     * {@inheritdoc}
     *
     * @param FieldMetadata $metadata
     * @param Version       $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $metadata->version = true;
        $metadata->versionType = $subject->type;
    }
}
