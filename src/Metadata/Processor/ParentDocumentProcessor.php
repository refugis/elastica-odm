<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\ParentDocument;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;

/** @Processor(annotation=ParentDocument::class) */
class ParentDocumentProcessor implements ProcessorInterface
{
    /**
     * {@inheritDoc}
     *
     * @param FieldMetadata  $metadata
     * @param ParentDocument $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $metadata->parentDocument = true;
    }
}
