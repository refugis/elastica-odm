<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation\Target;
use TypeError;

use function get_debug_type;
use function is_array;
use function is_string;
use function sprintf;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Embedded
{
    /**
     * The field name.
     */
    public ?string $name;

    /**
     * The embedded document class.
     *
     * @Required()
     */
    public string $targetClass;

    /**
     * Whether the properties in this embedded document should be parsed and indexed.
     */
    public bool $enabled;

    /**
     * Whether this field should be a collection or not.
     */
    public bool $multiple;

    /**
     * Whether this field should be a collection or not.
     */
    public bool $lazy;

    /** @param class-string|array<string, mixed> $targetClass */
    public function __construct($targetClass, ?string $name = null, bool $enabled = true, bool $multiple = false, bool $lazy = false)
    {
        if (is_string($targetClass)) {
            $data = ['targetClass' => $targetClass];
        } elseif (is_array($targetClass)) {
            $data = $targetClass;
        } else {
            throw new TypeError(sprintf('Argument #1 passed to %s must be a string. %s passed', __METHOD__, get_debug_type($targetClass)));
        }

        $this->targetClass = $data['targetClass'] ?? null;
        $this->name = $data['name'] ?? $name;
        $this->enabled = $data['enabled'] ?? $enabled;
        $this->multiple = $data['multiple'] ?? $multiple;
        $this->lazy = $data['lazy'] ?? $lazy;
    }
}
