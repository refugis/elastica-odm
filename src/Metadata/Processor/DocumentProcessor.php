<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\Document;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

/**
 * @Processor(annotation=Document::class)
 */
class DocumentProcessor implements ProcessorInterface
{
    /**
     * {@inheritdoc}
     *
     * @param DocumentMetadata $metadata
     * @param Document         $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $metadata->document = true;
        $metadata->collectionName = $subject->type;
        $metadata->customRepositoryClassName = $subject->repositoryClass;
    }
}
