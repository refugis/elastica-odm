<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Refugis\ODM\Elastica\Annotation\IndexName;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;
use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;

/**
 * @Processor(annotation=IndexName::class)
 */
class IndexNameProcessor implements ProcessorInterface
{
    /**
     * {@inheritdoc}
     *
     * @param FieldMetadata $metadata
     * @param IndexName     $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $metadata->indexName = true;
    }
}
