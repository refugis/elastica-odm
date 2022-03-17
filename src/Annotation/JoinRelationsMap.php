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
 * @Target({"CLASS"})
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class JoinRelationsMap
{
    /**
     * The join relations map.
     *
     * @Required()
     */
    public array $map;

    public function __construct(array $map)
    {
        $data = $map;
        if (! isset($map['map']) && ! isset($map['value'])) {
            $data = ['map' => $map];
        }

        $this->map = $data['map'] ?? $data['value'] ?? null;

        if (empty($this->map)) {
            throw new TypeError('Invalid join relations map. Must be not empty.');
        }
    }
}
