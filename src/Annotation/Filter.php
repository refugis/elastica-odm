<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;
use TypeError;

use function get_debug_type;
use function is_array;
use function is_string;
use function Safe\sprintf;

/**
 * @Annotation
 * @Target({"ANNOTATION"})
 */
#[Attribute]
final class Filter
{
    /**
     * The name of this filter.
     *
     * @Required()
     */
    public string $name;

    /**
     * The type of this filter.
     *
     * @Required()
     */
    public string $type;

    /**
     * Type-specific options.
     *
     * @var array<string, mixed>
     */
    public array $options;

    /**
     * @param string|array<string, mixed> $name
     * @param array<string, mixed> $options
     */
    public function __construct($name, string $type = 'raw', array $options = [])
    {
        if (is_string($name)) {
            $data = ['name' => $name];
        } elseif (is_array($name)) {
            $data = $name;
        } else {
            throw new TypeError(sprintf('Argument #1 passed to %s must be a string. %s passed', __METHOD__, get_debug_type($name)));
        }

        $this->name = $data['name'] ?? null;
        $this->type = $data['type'] ?? $type;
        $this->options = $data['options'] ?? $options;
    }
}
