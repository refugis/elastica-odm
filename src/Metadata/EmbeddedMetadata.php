<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata;

use Doctrine\Instantiator\Instantiator;
use Kcs\Metadata\PropertyMetadata;
use ReflectionProperty;

final class EmbeddedMetadata extends PropertyMetadata
{
    /**
     * The embedded target class.
     */
    public string $targetClass;

    /**
     * Whether the embedded document should be indexed.
     */
    public bool $enabled;

    public string $fieldName;
    public bool $multiple = false;
    public bool $lazy = false;

    /**
     * The instantiator used to build new object instances.
     */
    private Instantiator $instantiator;

    public function __construct(DocumentMetadata $documentMetadata, string $name)
    {
        parent::__construct($documentMetadata->name, $name);

        $this->instantiator = new Instantiator();
    }

    public function __wakeup(): void
    {
        parent::__wakeup();
        $this->instantiator = new Instantiator();
    }

    /**
     * Returns a new object instance.
     */
    public function newInstance(): object
    {
        return $this->instantiator->instantiate($this->targetClass);
    }

    public function getReflection(): ReflectionProperty
    {
        if (! isset($this->reflectionProperty)) {
            $this->reflectionProperty = new ReflectionProperty($this->class, $this->name);
            $this->reflectionProperty->setAccessible(true);
        }

        return $this->reflectionProperty;
    }

    /**
     * @return mixed
     */
    public function getValue(object $object)
    {
        return $this->getReflection()->getValue($object);
    }

    /**
     * @param mixed $value
     */
    public function setValue(object $object, $value): void
    {
        $this->getReflection()->setValue($object, $value);
    }
}
