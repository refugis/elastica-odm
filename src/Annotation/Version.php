<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation\Target;
use TypeError;

use function get_debug_type;
use function is_array;
use function is_string;
use function Safe\sprintf;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Version
{
    public const INTERNAL = 'internal';
    public const EXTERNAL = 'external';
    public const EXTERNAL_GTE = 'external_gte';

    /**
     * Whether the version type is external or internal.
     *
     * @Enum({Version::INTERNAL, Version::EXTERNAL})
     */
    public string $type;

    /**
     * @param string|array<string, mixed> $type
     */
    public function __construct($type)
    {
        if (is_string($type)) {
            $data = ['type' => $type];
        } elseif (is_array($type)) {
            $data = $type;
        } else {
            throw new TypeError(sprintf('Argument #1 passed to %s must be a string. %s passed', __METHOD__, get_debug_type($type)));
        }

        $this->type = $data['type'] ?? self::INTERNAL;
    }
}
