<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata;

use Doctrine\Instantiator\Instantiator;
use Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use InvalidArgumentException;
use Kcs\Metadata\ClassMetadata;
use Kcs\Metadata\MetadataInterface;
use ReflectionClass;
use Refugis\ODM\Elastica\Exception\RuntimeException;

use function array_merge;
use function array_unique;
use function reset;
use function Safe\sort;
use function Safe\sprintf;

final class DocumentMetadata extends ClassMetadata implements ClassMetadataInterface
{
    public const GENERATOR_TYPE_NONE = 0;
    public const GENERATOR_TYPE_AUTO = 1;

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
     * The elastic index/type name.
     */
    public string $collectionName;

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
     */
    public array $eagerFieldNames = [];

    /**
     * An array containing all the field names.
     */
    public array $fieldNames = [];

    /**
     * An array containing all the field names.
     */
    public array $embeddedFieldNames = [];

    /**
     * The join settings.
     *
     * @var array<string, mixed>|null
     * @phpstan-var array{type: string, fieldName: string, parentClass?: class-string, relations?: array<string, array<string>>}|null
     */
    public ?array $join = null;

    /**
     * The join parent field.
     */
    public ?string $parentField = null;

    /**
     * While processing joins, this property will contain all the children class names (all levels).
     *
     * @var array<class-string>
     */
    public array $childrenClasses = [];

    /**
     * Gets the index dynamic settings.
     */
    public array $dynamicSettings = [];

    /**
     * Gets the index static settings.
     */
    public array $staticSettings = [];

    /**
     * The instantiator used to build new object instances.
     */
    private Instantiator $instantiator;

    public function __construct(ReflectionClass $class)
    {
        parent::__construct($class);

        $this->instantiator = new Instantiator();
        $this->document = false;
        $this->embeddable = false;
    }

    public function __wakeup(): void
    {
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
        } elseif ($metadata instanceof FieldMetadata) {
            $this->fieldNames[] = $metadata->fieldName;
            sort($this->fieldNames);
        }

        if ($metadata->lazy ?? false) {
            return;
        }

        $this->eagerFieldNames[] = $metadata->fieldName;
        sort($this->eagerFieldNames);
    }

    /**
     * {@inheritdoc}
     *
     * @param self $metadata
     */
    public function merge(MetadataInterface $metadata): void
    {
        parent::merge($metadata);

        $this->customRepositoryClassName ??= $metadata->customRepositoryClassName;
        $this->collectionName ??= $metadata->collectionName;
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
     * {@inheritdoc}
     */
    public function getIdentifier(): array
    {
        return [$this->identifier->fieldName];
    }

    /**
     * {@inheritdoc}
     */
    public function isIdentifier($fieldName): bool
    {
        return $this->identifier->fieldName === $fieldName;
    }

    /**
     * {@inheritdoc}
     */
    public function hasField($fieldName): bool
    {
        return isset($this->attributesMetadata[$fieldName]) && $this->attributesMetadata[$fieldName]->field;
    }

    /**
     * {@inheritdoc}
     */
    public function hasAssociation($fieldName): bool
    {
        // TODO: Implement hasAssociation() method.
    }

    /**
     * {@inheritdoc}
     */
    public function isSingleValuedAssociation($fieldName): bool
    {
        // TODO: Implement isSingleValuedAssociation() method.
    }

    /**
     * {@inheritdoc}
     */
    public function isCollectionValuedAssociation($fieldName): bool
    {
        // TODO: Implement isCollectionValuedAssociation() method.
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldNames(): array
    {
        return $this->fieldNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifierFieldNames(): array
    {
        return [$this->identifier->fieldName];
    }

    /**
     * {@inheritdoc}
     */
    public function getAssociationNames(): array
    {
        $associations = [];
        if ($this->join !== null && isset($this->join['parentClass'])) {
            $associations[] = self::JOIN_FIELD_ASSOCIATION;
        }

        return $associations;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getAssociationTargetClass($assocName): string
    {
        if ($assocName === self::JOIN_FIELD_ASSOCIATION) {
            return $this->join['parentClass'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isAssociationInverseSide($assocName): bool
    {
        // TODO: Implement isAssociationInverseSide() method.
    }

    /**
     * {@inheritdoc}
     */
    public function getAssociationMappedByTargetField($assocName): string
    {
        // TODO: Implement getAssociationMappedByTargetField() method.
    }

    /**
     * {@inheritdoc}
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

    public function getSingleIdentifier($object): ?string
    {
        $id = $this->getIdentifierValues($object);
        if (empty($id)) {
            return null;
        }

        return reset($id);
    }

    public function setIdentifierValue($object, $value): void
    {
        $this->identifier->setValue($object, $value);
    }

    public function getField(string $fieldName)
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
}
