<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata;

use Kcs\Metadata\PropertyMetadata;

class FieldMetadata extends PropertyMetadata
{
    public bool $identifier = false;
    public bool $field = false;
    public bool $indexName = false;
    public bool $typeName = false;
    public string $fieldName;
    public string $type;
    public bool $multiple = false;
    public array $options = [];
    public bool $lazy = false;
    public DocumentMetadata $documentMetadata;
    private \ReflectionProperty $reflectionProperty;

    public function __construct(DocumentMetadata $class, string $name)
    {
        $this->documentMetadata = $class;
        $this->fieldName = $name;
        $this->type = 'raw';

        parent::__construct($class->name, $name);
    }

    public function getReflection(): \ReflectionProperty
    {
        if (! isset($this->reflectionProperty)) {
            $this->reflectionProperty = new \ReflectionProperty($this->class, $this->name);
            $this->reflectionProperty->setAccessible(true);
        }

        return $this->reflectionProperty;
    }

    public function getValue($object)
    {
        return $this->getReflection()->getValue($object);
    }

    public function setValue($object, $value): void
    {
        $reflection = $this->getReflection();
        $reflection->setValue($object, $value);
    }

    public function isStored(): bool
    {
        return ! (
            $this->identifier ||
            $this->indexName ||
            $this->typeName
        );
    }
}
