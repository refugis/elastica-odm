<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata;

use Doctrine\Instantiator\Instantiator;
use Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use InvalidArgumentException;
use Kcs\Metadata\ClassMetadata;
use Kcs\Metadata\MetadataInterface;
use Kcs\Metadata\PropertyMetadata;
use ReflectionClass;
use Refugis\ODM\Elastica\Annotation\Version;
use Refugis\ODM\Elastica\Exception\RuntimeException;

use function array_filter;
use function array_merge;
use function array_unique;
use function reset;
use function Safe\sort;
use function Safe\sprintf;

/**
 * @template T of object
 * @implements ClassMetadataInterface<T>
 */
final class DocumentMetadata extends ClassMetadata implements ClassMetadataInterface
{
    public const GENERATOR_TYPE_NONE = 0;
    public const GENERATOR_TYPE_AUTO = 1;

    public const INHERITANCE_TYPE_SINGLE_INDEX = 0;
    public const INHERITANCE_TYPE_PARENT_CHILD = 1;
    public const INHERITANCE_TYPE_INDEX_PER_CLASS = 1;

    private const JOIN_FIELD_ASSOCIATION = '$$join';

    /**
     * Whether this class is representing a document.
     */
    public bool $document;

    /**
     * Whether this class is representing an embeddable document.
     */
    public bool $embeddable;

    /**
     * Whether this class is extended by a document class and its properties should be mapped.
     */
    public bool $mappedSuperclass;

    /**
     * Whether this document is readonly. Not applicable to embedded documents.
     */
    public bool $isReadOnly;

    /**
     * The elastic index/type name.
     */
    public string $collectionName;

    /**
     * Whether the collection name represents multiple indexes/aliases.
     */
    public bool $multiIndex;

    /**
     * The identifier field name.
     */
    public ?FieldMetadata $identifier;

    /**
     * Identifier generator type.
     */
    public int $idGeneratorType;

    /**
     * The fully-qualified class name of the custom repository class.
     * Optional.
     */
    public ?string $customRepositoryClassName = null;

    /**
     * An array containing all the non-lazy field names.
     *
     * @var string[]
     */
    public array $eagerFieldNames = [];

    /**
     * @internal
     *
     * @var string[]|null
     */
    public ?array $sourceEagerFields = null;

    /**
     * An array containing all the field names.
     *
     * @var string[]
     */
    public array $fieldNames = [];

    /**
     * An array containing all the field names.
     *
     * @var string[]
     */
    public array $embeddedFieldNames = [];

    /**
     * Gets the index dynamic settings.
     *
     * @var array<string, mixed>
     */
    public array $dynamicSettings = [];

    /**
     * Gets the index static settings.
     *
     * @var array<string, mixed>
     */
    public array $staticSettings = [];

    /**
     * Whether to call refresh on index after the uow commit operation.
     */
    public bool $refreshOnCommit;

    /**
     * Whether the version type is external or internal.
     */
    public ?string $versionType;

    /**
     * The instantiator used to build new object instances.
     */
    private Instantiator $instantiator;

    /**
     * The inheritance type of the current class hierarchy.
     */
    public ?int $inheritanceType;

    /**
     * The discriminator map for the current class hierarchy.
     *
     * @var array<string, class-string>
     */
    public ?array $discriminatorMap;

    /**
     * The discriminator field name for the current class hierarchy.
     */
    public ?string $discriminatorField;

    /**
     * The discriminator value for the current class.
     */
    public ?string $discriminatorValue;

    /**
     * The join field name for the current class hierarchy.
     */
    public ?string $joinField;

    /**
     * The join parent class name.
     */
    public ?string $joinParentClass;

    /**
     * The relations map for parent-child inheritance.
     *
     * @var array<string, mixed>
     */
    public ?array $joinRelationMap;

    public function __construct(ReflectionClass $class)
    {
        parent::__construct($class);

        $this->instantiator = new Instantiator();
        $this->document = false;
        $this->embeddable = false;
        $this->mappedSuperclass = false;
        $this->isReadOnly = false;
        $this->refreshOnCommit = true;
        $this->versionType = Version::INTERNAL;
        $this->inheritanceType = null;
        $this->discriminatorMap = null;
        $this->discriminatorField = null;
        $this->discriminatorValue = null;
        $this->joinField = null;
        $this->joinRelationMap = null;
        $this->joinParentClass = null;
    }

    public function __wakeup(): void
    {
        parent::__wakeup();

        $this->instantiator = new Instantiator();
    }

    public function addAttributeMetadata(MetadataInterface $metadata): void
    {
        parent::addAttributeMetadata($metadata);

        if (! isset($metadata->fieldName)) {
            return;
        }

        if ($metadata instanceof EmbeddedMetadata) {
            $this->embeddedFieldNames[] = $metadata->fieldName;
            sort($this->embeddedFieldNames);
        } elseif ($metadata instanceof FieldMetadata && ! $metadata->identifier) {
            $this->fieldNames[] = $metadata->fieldName;
            sort($this->fieldNames);

            if ($metadata->version) {
                $this->versionType = $metadata->versionType;
            }
        }

        if ($metadata->lazy ?? false) {
            return;
        }

        $this->eagerFieldNames[] = $metadata->fieldName;
        sort($this->eagerFieldNames);
    }

    /**
     * {@inheritDoc}
     *
     * @param self $metadata
     */
    public function merge(MetadataInterface $metadata): void
    {
        parent::merge($metadata);

        $this->customRepositoryClassName ??= $metadata->customRepositoryClassName;
        $this->collectionName ??= $metadata->collectionName;
        $this->multiIndex ??= $metadata->multiIndex;
        $this->identifier ??= $metadata->identifier;
        $this->idGeneratorType ??= $metadata->idGeneratorType;

        $this->eagerFieldNames = array_unique(array_merge($this->eagerFieldNames, $metadata->eagerFieldNames));
        sort($this->eagerFieldNames);
    }

    /**
     * Returns a new object instance.
     */
    public function newInstance(): object
    {
        return $this->instantiator->instantiate($this->name);
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifier(): array
    {
        return [$this->identifier->fieldName];
    }

    /**
     * {@inheritDoc}
     */
    public function isIdentifier($fieldName): bool
    {
        return $this->identifier->fieldName === $fieldName;
    }

    /**
     * {@inheritDoc}
     */
    public function hasField($fieldName): bool
    {
        return isset($this->attributesMetadata[$fieldName]) && $this->attributesMetadata[$fieldName]->field;
    }

    /**
     * {@inheritDoc}
     */
    public function hasAssociation($fieldName): bool
    {
        // TODO: Implement hasAssociation() method.
    }

    /**
     * {@inheritDoc}
     */
    public function isSingleValuedAssociation($fieldName): bool
    {
        // TODO: Implement isSingleValuedAssociation() method.
    }

    /**
     * {@inheritDoc}
     */
    public function isCollectionValuedAssociation($fieldName): bool
    {
        // TODO: Implement isCollectionValuedAssociation() method.
    }

    /**
     * {@inheritDoc}
     */
    public function getFieldNames(): array
    {
        return $this->fieldNames;
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifierFieldNames(): array
    {
        return [$this->identifier->fieldName];
    }

    /**
     * {@inheritDoc}
     */
    public function getAssociationNames(): array
    {
        $associations = [];
        if ($this->joinParentClass !== null) {
            $associations[] = self::JOIN_FIELD_ASSOCIATION;
        }

        return $associations;
    }

    /**
     * {@inheritDoc}
     */
    public function getTypeOfField($fieldName): string
    {
        $field = $this->getField($fieldName);
        if (! $field instanceof FieldMetadata) {
            throw new RuntimeException(sprintf('Field "%s" does not exist or is not a valid field name.', $fieldName));
        }

        return $field->type;
    }

    /**
     * {@inheritDoc}
     */
    public function getAssociationTargetClass($assocName): string
    {
        if ($assocName === self::JOIN_FIELD_ASSOCIATION) {
            return $this->joinParentClass;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isAssociationInverseSide($assocName): bool
    {
        // TODO: Implement isAssociationInverseSide() method.
    }

    /**
     * {@inheritDoc}
     */
    public function getAssociationMappedByTargetField($assocName): string
    {
        // TODO: Implement getAssociationMappedByTargetField() method.
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifierValues($object): array
    {
        $class = $this->name;
        if (! $object instanceof $class) {
            throw new InvalidArgumentException('Unexpected object class');
        }

        if ($this->identifier === null) {
            return [];
        }

        $property = $this->identifier->getReflection();

        return [$this->identifier->fieldName => $property->getValue($object)];
    }

    public function getSingleIdentifierFieldName(): string
    {
        return $this->identifier->fieldName;
    }

    public function getSingleIdentifier(object $object): ?string
    {
        $id = $this->getIdentifierValues($object);
        if (empty($id)) {
            return null;
        }

        return reset($id);
    }

    public function setIdentifierValue(object $object, ?string $value): void
    {
        $this->identifier->setValue($object, $value);
    }

    public function getSequenceNumber(object $object): ?int
    {
        foreach ($this->attributesMetadata as $metadata) {
            if (! $metadata instanceof FieldMetadata || ! $metadata->seqNo) {
                continue;
            }

            return $metadata->getValue($object);
        }

        return null;
    }

    public function getPrimaryTerm(object $object): ?int
    {
        foreach ($this->attributesMetadata as $metadata) {
            if (! $metadata instanceof FieldMetadata || ! $metadata->primaryTerm) {
                continue;
            }

            return $metadata->getValue($object);
        }

        return null;
    }

    public function getIndexName(object $object): ?string
    {
        foreach ($this->attributesMetadata as $metadata) {
            if (! $metadata instanceof FieldMetadata || ! $metadata->indexName) {
                continue;
            }

            return $metadata->getValue($object);
        }

        return null;
    }

    public function getVersion(object $object): ?int
    {
        foreach ($this->attributesMetadata as $metadata) {
            if (! $metadata instanceof FieldMetadata || ! $metadata->version) {
                continue;
            }

            return $metadata->getValue($object);
        }

        return null;
    }

    public function getField(string $fieldName): ?PropertyMetadata
    {
        foreach ($this->attributesMetadata as $metadata) {
            if (! $metadata instanceof FieldMetadata && ! $metadata instanceof EmbeddedMetadata) {
                continue;
            }

            if ($metadata->fieldName === $fieldName) {
                return $metadata;
            }
        }

        return null;
    }

    public function getParentDocumentField(): ?FieldMetadata
    {
        foreach ($this->attributesMetadata as $metadata) {
            if (! $metadata instanceof FieldMetadata || ! $metadata->parentDocument) {
                continue;
            }

            return $metadata;
        }

        return null;
    }

    public function getParentDocument(object $object): ?object
    {
        $field = $this->getParentDocumentField();
        if ($field !== null) {
            return $field->getValue($object);
        }

        return null;
    }

    public function finalize(): void
    {
        $identifier = null;
        foreach ($this->getAttributesMetadata() as $attributeMetadata) {
            if (! $attributeMetadata instanceof FieldMetadata) {
                continue;
            }

            if (! $attributeMetadata->identifier) {
                continue;
            }

            $identifier = $attributeMetadata;
        }

        $this->identifier = $identifier;
        $this->eagerFieldNames = array_filter($this->eagerFieldNames);
    }

    /** @return string[] */
    public function getSourceEagerFields(): array
    {
        if ($this->sourceEagerFields !== null) {
            return $this->sourceEagerFields;
        }

        $fields = [];
        foreach ($this->eagerFieldNames as $fieldName) {
            $field = $this->getField($fieldName);
            if ($field instanceof FieldMetadata && (! $field->isStored() || $field->parentDocument)) {
                continue;
            }

            $fields[] = $fieldName;
        }

        if ($this->discriminatorField !== null) {
            $fields[] = $this->discriminatorField;
        }

        if ($this->joinField !== null) {
            $fields[] = $this->joinField;
        }

        return $this->sourceEagerFields = $fields;
    }
}
