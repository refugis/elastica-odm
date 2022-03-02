<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata;

use Kcs\Metadata\PropertyMetadata;
use ReflectionProperty;

class FieldMetadata extends PropertyMetadata
{
    public bool $identifier = false;
    public bool $field = false;
    public bool $indexName = false;
    public bool $typeName = false;
    public bool $seqNo = false;
    public bool $primaryTerm = false;
    public string $fieldName;
    public string $type;
    public bool $multiple = false;
    /** @var array<string, mixed> */
    public array $options = [];
    public bool $lazy = false;
    public DocumentMetadata $documentMetadata;
    private ReflectionProperty $reflectionProperty;

    public function __construct(DocumentMetadata $class, string $name)
    {
        $this->documentMetadata = $class;
        $this->fieldName = $name;
        $this->type = 'raw';

        parent::__construct($class->name, $name);
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
        $reflection = $this->getReflection();
        $reflection->setValue($object, $value);
    }

    public function isStored(): bool
    {
        return ! (
            $this->identifier ||
            $this->indexName ||
            $this->typeName ||
            $this->seqNo ||
            $this->primaryTerm
        );
    }
}
