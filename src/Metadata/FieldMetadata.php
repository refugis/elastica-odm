<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata;

use Error;
use Kcs\Metadata\PropertyMetadata;
use ReflectionNamedType;
use ReflectionProperty;
use Refugis\ODM\Elastica\Annotation\Version;
use Refugis\ODM\Elastica\Exception\UninitializedPropertyException;

use function preg_match;
use function sprintf;
use function str_contains;

use const PHP_VERSION_ID;

class FieldMetadata extends PropertyMetadata
{
    public bool $identifier = false;
    public bool $field = false;
    public bool $indexName = false;
    public bool $typeName = false;
    public bool $seqNo = false;
    public bool $primaryTerm = false;
    public bool $version = false;
    public string $versionType = Version::INTERNAL;
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
        $reflection = $this->getReflection();

        try {
            return $reflection->getValue($object);
        } catch (Error $e) {
            // handle uninitialized properties in PHP >= 7.4
            if (preg_match('/^Typed property ([\w\\\\@]+)::\$(\w+) must not be accessed before initialization$/', $e->getMessage(), $matches)) {
                $r = new ReflectionProperty(str_contains($matches[1], '@anonymous') ? $this->class : $matches[1], $matches[2]);
                $type = ($type = $r->getType()) instanceof ReflectionNamedType ? $type->getName() : (string) $type;

                throw new UninitializedPropertyException(sprintf('The property "%s::$%s" is not readable because it is typed "%s". You should initialize it or declare a default value instead.', $matches[1], $r->getName(), $type), 0, $e);
            }

            throw $e;
        }
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
            $this->primaryTerm ||
            $this->version
        );
    }
}
