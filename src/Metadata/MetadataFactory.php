<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata;

use Doctrine\Common\EventManager;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Kcs\Metadata\ClassMetadataInterface;
use Kcs\Metadata\Exception\InvalidMetadataException;
use Kcs\Metadata\Factory\AbstractMetadataFactory;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;
use Refugis\ODM\Elastica\Annotation\InheritanceType;
use Refugis\ODM\Elastica\Metadata\Loader\LoaderInterface;

use function array_key_exists;
use function array_search;
use function array_values;
use function assert;
use function get_parent_class;
use function is_array;
use function method_exists;
use function preg_replace;
use function sprintf;
use function str_replace;

class MetadataFactory extends AbstractMetadataFactory implements ClassMetadataFactory
{
    private LoaderInterface $loader;
    private EventManager $eventManager;
    private ?CacheItemPoolInterface $cache;

    public function __construct(LoaderInterface $loader, ?CacheItemPoolInterface $cache = null)
    {
        parent::__construct($loader, null, $cache);

        $this->loader = $loader;
        $this->cache = $cache;
    }

    /**
     * Sets the event manager for this metadata factory.
     */
    public function setEventManager(EventManager $eventManager): void
    {
        $this->eventManager = $eventManager;
    }

    /**
     * Gets all the metadata available in this factory.
     *
     * @return DocumentMetadata[]
     */
    public function getAllMetadata(): array
    {
        $metadatas = [];
        foreach ($this->loader->getAllClassNames() as $className) {
            do {
                $metadatas[$className] = $this->getMetadataFor($className);
                $className = get_parent_class($className);
            } while ($className);
        }

        return array_values($metadatas);
    }

    /**
     * @param class-string $className
     * @param DocumentMetadata $class
     */
    public function setMetadataFor($className, $class): void // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
    {
        if (method_exists(AbstractMetadataFactory::class, 'setMetadataFor')) {
            parent::setMetadataFor($className, $class);

            return;
        }

        if ($this->cache === null) {
            return;
        }

        $cacheKey = preg_replace('#[\{\}\(\)/\\\\@:]#', '_', str_replace('_', '__', $className));
        $item = $this->cache->getItem($cacheKey);
        $item->set($class);
        $this->cache->save($item);
    }

    /**
     * {@inheritDoc}
     */
    public function isTransient($className): bool
    {
        $class = $this->getMetadataFor($className);

        return $class instanceof DocumentMetadata && $class->document;
    }

    protected function dispatchClassMetadataLoadedEvent(ClassMetadataInterface $classMetadata): void
    {
        // @todo
    }

    protected function validate(ClassMetadataInterface $classMetadata): void
    {
        if (! $classMetadata instanceof DocumentMetadata) {
            return;
        }

        $identifier = null;
        foreach ($classMetadata->getAttributesMetadata() as $attributeMetadata) {
            if ($attributeMetadata instanceof EmbeddedMetadata) {
                $targetClass = $attributeMetadata->targetClass;
                $targetMetadata = $this->getMetadataFor($targetClass);
                if (! $targetMetadata instanceof DocumentMetadata || ! $targetMetadata->embeddable) {
                    throw new InvalidMetadataException(sprintf('Embedded document "%s" is not marked as embeddable', $targetClass));
                }

                continue;
            }

            $count = 0;
            if (! $attributeMetadata instanceof FieldMetadata) {
                continue;
            }

            if ($attributeMetadata->identifier) {
                if ($identifier !== null) {
                    throw new InvalidMetadataException('@DocumentId should be declared at most once per class.');
                }

                $identifier = $attributeMetadata;
                ++$count;
            }

            if ($attributeMetadata->typeName || $attributeMetadata->indexName || $attributeMetadata->seqNo || $attributeMetadata->primaryTerm || $attributeMetadata->version) {
                ++$count;
            }

            if ($count > 1) {
                throw new InvalidMetadataException(sprintf(
                    '@DocumentId, @IndexName, @TypeName, @SequenceNumber, @PrimaryTerm and @Version are mutually exclusive. Please select one for "%s"',
                    $attributeMetadata->getName(),
                ));
            }
        }

        unset($attributeMetadata);

        if ($identifier === null && ! $classMetadata->embeddable) {
            throw new InvalidMetadataException(sprintf('At least one @DocumentId is required for an elastic document. Please add one to "%s" class', $classMetadata->getName()));
        }

        $reflectionClass = $classMetadata->getReflectionClass();
        $parentClass = $reflectionClass->getParentClass();
        if ($parentClass) {
            $parentMetadata = $this->getMetadataFor($parentClass->getName());
            assert($parentMetadata instanceof DocumentMetadata);
            if ($parentMetadata->inheritanceType === null && ! $parentMetadata->mappedSuperclass) {
                throw new InvalidMetadataException(sprintf('Class "%s" extends "%s" which is not mapped. Please add @MappedSuperclass to the parent class or define an inheritance type.', $classMetadata->getName(), $parentMetadata->getName()));
            }

            $classMetadata->inheritanceType = $parentMetadata->inheritanceType;
            $classMetadata->discriminatorField = $parentMetadata->discriminatorField;
            $classMetadata->discriminatorMap = $parentMetadata->discriminatorMap;
            $classMetadata->joinField = $parentMetadata->joinField;
            $classMetadata->joinRelationMap = $parentMetadata->joinRelationMap;

            if ($classMetadata->inheritanceType === DocumentMetadata::INHERITANCE_TYPE_SINGLE_INDEX || $classMetadata->inheritanceType === DocumentMetadata::INHERITANCE_TYPE_PARENT_CHILD) {
                $classMetadata->collectionName = $parentMetadata->collectionName;
                $classMetadata->refreshOnCommit = $parentMetadata->refreshOnCommit;
            }
        }

        if ($classMetadata->inheritanceType === DocumentMetadata::INHERITANCE_TYPE_SINGLE_INDEX || $classMetadata->inheritanceType === DocumentMetadata::INHERITANCE_TYPE_PARENT_CHILD) {
            if (empty($classMetadata->discriminatorMap)) {
                throw new InvalidMetadataException(sprintf(
                    'Class "%s" has inheritance type %s but no discriminator map has been defined.',
                    $classMetadata->getName(),
                    $classMetadata->inheritanceType === DocumentMetadata::INHERITANCE_TYPE_SINGLE_INDEX ? InheritanceType::SINGLE_INDEX : InheritanceType::PARENT_CHILD,
                ));
            }
        }

        if ($classMetadata->inheritanceType === DocumentMetadata::INHERITANCE_TYPE_PARENT_CHILD) {
            if (empty($classMetadata->discriminatorMap)) {
                throw new InvalidMetadataException(sprintf(
                    'Class "%s" has inheritance type %s but no relations map has been defined.',
                    $classMetadata->getName(),
                    InheritanceType::PARENT_CHILD,
                ));
            }
        }

        if (! $reflectionClass->isAbstract()) {
            if ($classMetadata->inheritanceType === DocumentMetadata::INHERITANCE_TYPE_SINGLE_INDEX || $classMetadata->inheritanceType === DocumentMetadata::INHERITANCE_TYPE_PARENT_CHILD) {
                $value = array_search($classMetadata->getName(), $classMetadata->discriminatorMap, true);
                if ($value === false) {
                    throw new InvalidMetadataException(sprintf('Class "%s" is not present in the discriminator map. Please add it with a unique discriminator value.', $classMetadata->getName()));
                }

                $classMetadata->discriminatorValue = $value;
            }

            if ($classMetadata->inheritanceType === DocumentMetadata::INHERITANCE_TYPE_PARENT_CHILD) {
                $isRoot = array_key_exists($classMetadata->name, $classMetadata->joinRelationMap);
                $parentClass = null;
                $isPresentInMap = static function (string $key, array $map) use (&$isPresentInMap) {
                    foreach ($map as $mapKey => $value) {
                        if ($key === $mapKey) {
                            return true;
                        }

                        if (! is_array($value)) {
                            continue;
                        }

                        $result = $isPresentInMap($key, $value);
                        if ($result === true) {
                            return $mapKey;
                        }

                        if ($result !== false) {
                            return $result;
                        }
                    }

                    return false;
                };

                if (! $isRoot) {
                    $parentClass = $isPresentInMap($classMetadata->name, $classMetadata->joinRelationMap);
                    if (! $parentClass) {
                        throw new InvalidMetadataException(sprintf('Class "%s" is not present in the join relations map.', $classMetadata->getName()));
                    }
                }

                $classMetadata->joinParentClass = $parentClass;

                if (! $isRoot) {
                    $parentField = null;
                    foreach ($classMetadata->getAttributesMetadata() as $attributeMetadata) {
                        if (! $attributeMetadata instanceof FieldMetadata) {
                            continue;
                        }

                        if (! $attributeMetadata->parentDocument) {
                            continue;
                        }

                        if ($parentField !== null) {
                            throw new InvalidMetadataException(sprintf('Class "%s" declared multiple parent field, you can only set one per class.', $classMetadata->getName()));
                        }

                        $parentField = $attributeMetadata;
                    }

                    if ($parentField === null) {
                        throw new InvalidMetadataException(sprintf('Class "%s" has no parent field, but is not at relations root-level. Please add one.', $classMetadata->getName()));
                    }
                }
            }
        }

        if ($classMetadata->inheritanceType !== DocumentMetadata::INHERITANCE_TYPE_PARENT_CHILD) {
            return;
        }

        if (empty($classMetadata->joinField)) {
            throw new InvalidMetadataException(sprintf('Class "%s" has empty join field name.', $classMetadata->getName()));
        }

        foreach ($classMetadata->getAttributesMetadata() as $attributeMetadata) {
            if (! $attributeMetadata instanceof FieldMetadata && ! $attributeMetadata instanceof EmbeddedMetadata) {
                continue;
            }

            if ($attributeMetadata->fieldName === $classMetadata->joinField) {
                throw new InvalidMetadataException(sprintf('Join field name collides with field "%s" on "%s".', $attributeMetadata->name, $classMetadata->getName()));
            }
        }

        unset($attributeMetadata);
    }

    protected function createMetadata(ReflectionClass $class): ClassMetadataInterface
    {
        return new DocumentMetadata($class);
    }
}
