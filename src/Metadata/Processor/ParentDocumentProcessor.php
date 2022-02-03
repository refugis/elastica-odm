<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Kcs\Metadata\Exception\InvalidMetadataException;
use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\ParentDocument;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;

/**
 * @Processor(annotation=ParentDocument::class)
 */
class ParentDocumentProcessor implements ProcessorInterface
{
    /**
     * {@inheritdoc}
     *
     * @param FieldMetadata  $metadata
     * @param ParentDocument $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $documentMetadata = $metadata->documentMetadata;
        if ($documentMetadata->join === null || ! isset($documentMetadata->join['type'])) {
            throw new InvalidMetadataException('ParentDocument set on document without join attribute set.');
        }

        $reflectionType = $metadata->getReflection()->getType();
        $type = $subject->target ?? ($reflectionType !== null ? $reflectionType->getName() : null);
        if ($type === null) {
            throw new InvalidMetadataException('Cannot guess the parent document class. Please add a target attribute.');
        }

        $documentMetadata->join['parentClass'] = $type;
        $documentMetadata->parentField = $metadata->name;
    }
}
