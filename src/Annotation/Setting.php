<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;
use TypeError;

use function get_debug_type;
use function in_array;
use function is_array;
use function is_string;
use function Safe\sprintf;

use const Attribute;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
#[Attribute]
final class Setting
{
    /**
     * Id generator strategy.
     *
     * @Enum({"auto", "static", "dynamic"})
     */
    public string $type;

    /**
     * The setting key.
     *
     * @Required()
     */
    public string $key;

    /**
     * The setting value.
     *
     * @Required()
     * @var mixed
     */
    public $value;

    public function __construct($type = 'auto', ?string $key = null, $value = null)
    {
        if (is_string($type)) {
            $data = ['type' => $type];
        } elseif (is_array($type)) {
            $data = $type;
        } else {
            throw new TypeError(sprintf('Argument #1 passed to %s must be a string. %s passed', __METHOD__, get_debug_type($type)));
        }

        $this->type = $data['type'] ?? $type;
        $this->key = $data['key'] ?? null;
        $this->value = $data['value'] ?? $value;

        if (! in_array($this->type, ['auto', 'static', 'dynamic'], true)) {
            throw new TypeError(sprintf('Setting type must be one of "auto", "static" or "dynamic". "%s" given', $type));
        }
    }
}
