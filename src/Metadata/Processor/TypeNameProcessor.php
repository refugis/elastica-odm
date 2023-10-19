<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\TypeName;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;

/** @Processor(annotation=TypeName::class) */
class TypeNameProcessor implements ProcessorInterface
{
    /**
     * {@inheritDoc}
     *
     * @param FieldMetadata $metadata
     * @param TypeName      $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $metadata->typeName = true;
    }
}
