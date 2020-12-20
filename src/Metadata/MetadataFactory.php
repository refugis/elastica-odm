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
use Refugis\ODM\Elastica\Metadata\Loader\LoaderInterface;

use function array_filter;

class MetadataFactory extends AbstractMetadataFactory implements ClassMetadataFactory
{
    private LoaderInterface $loader;
    private EventManager $eventManager;

    public function __construct(LoaderInterface $loader, ?CacheItemPoolInterface $cache = null)
    {
        $this->loader = $loader;

        parent::__construct($loader, null, $cache);
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
            $metadatas[] = $this->getMetadataFor($className);
        }

        return $metadatas;
    }

    public function setMetadataFor($className, $class)
    {
        // @todo
    }

    /**
     * {@inheritdoc}
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
        foreach ($classMetadata->getAttributesMetadata() as $attributesMetadata) {
            $count = 0;

            if (! $attributesMetadata instanceof FieldMetadata) {
                continue;
            }

            if ($attributesMetadata->identifier) {
                if ($identifier !== null) {
                    throw new InvalidMetadataException('@DocumentId should be declared at most once per class.');
                }

                $identifier = $attributesMetadata;
                ++$count;
            }

            if ($attributesMetadata->typeName) {
                ++$count;
            }

            if ($attributesMetadata->indexName) {
                ++$count;
            }

            if ($count > 1) {
                throw new InvalidMetadataException('@DocumentId, @IndexName and @TypeName are mutually exclusive. Please select one for "' . $attributesMetadata->getName() . '"');
            }
        }

        if ($identifier === null) {
            throw new InvalidMetadataException('At least one @DocumentId is required for an elastic document');
        }

        $classMetadata->identifier = $identifier;
        $classMetadata->eagerFieldNames = array_filter($classMetadata->eagerFieldNames);
    }

    protected function createMetadata(ReflectionClass $class): ClassMetadataInterface
    {
        return new DocumentMetadata($class);
    }
}
