<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Doctrine\Common\Annotations\Annotation\Target;
use function Safe\sprintf;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
#[Attribute]
final class Field
{
    /**
     * The field name.
     */
    public ?string $name;

    /**
     * Field type.
     */
    public string $type;

    /**
     * Whether this field should be a collection or not.
     */
    public bool $multiple;

    /**
     * Field options.
     *
     * @var array<string, mixed>
     */
    public array $options;

    /**
     * Whether to load this field in lazy mode.
     */
    public bool $lazy;

    public function __construct($name, string $type = 'raw', bool $multiple = false, array $options = [], bool $lazy = false)
    {
        if (is_string($name)) {
            $data = [ 'name' => $name ];
        } elseif (is_array($name)) {
            $data = $name;
        } else {
            throw new \TypeError(sprintf('Argument #1 passed to %s must be a string. %s passed', __METHOD__, get_debug_type($name)));
        }

        $this->name = $data['name'] ?? null;
        $this->type = $data['type'] ?? $type;
        $this->multiple = $data['multiple'] ?? $multiple;
        $this->options = $data['options'] ?? $options;
        $this->lazy = $data['lazy'] ?? $lazy;
    }
}
