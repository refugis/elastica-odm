<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Doctrine\Inflector\Rules\English\InflectorFactory;
use Kcs\Metadata\Exception\InvalidMetadataException;
use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\InheritanceType;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

use function Safe\sprintf;

/** @Processor(annotation=InheritanceType::class) */
class InheritanceTypeProcessor implements ProcessorInterface
{
    /**
     * {@inheritDoc}
     *
     * @param DocumentMetadata $metadata
     * @param InheritanceType $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        switch ($subject->type) {
            case InheritanceType::SINGLE_INDEX:
                $metadata->inheritanceType = DocumentMetadata::INHERITANCE_TYPE_SINGLE_INDEX;
                $metadata->discriminatorField ??= 'discr';
                $metadata->discriminatorValue ??= $this->slugify($metadata->getReflectionClass()->getShortName());
                break;

            case InheritanceType::PARENT_CHILD:
                $metadata->inheritanceType = DocumentMetadata::INHERITANCE_TYPE_PARENT_CHILD;
                $metadata->joinField ??= 'join';
                $metadata->discriminatorField ??= 'discr';
                $metadata->discriminatorValue ??= $this->slugify($metadata->getReflectionClass()->getShortName());
                break;

            case InheritanceType::INDEX_PER_CLASS:
                $metadata->inheritanceType = DocumentMetadata::INHERITANCE_TYPE_INDEX_PER_CLASS;
                break;

            default:
                throw new InvalidMetadataException(sprintf('Unknown inheritance type "%s"', $subject->type));
        }
    }

    /**
     * Build a collection name from class name.
     */
    private function slugify(string $name): string
    {
        static $inflector = null;
        if ($inflector === null) {
            $inflector = (new InflectorFactory())->build();
        }

        return $inflector->tableize($name);
    }
}
