<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;
use TypeError;

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
     * @var array<string, mixed>
     */
    public array $map;

    /**
     * @param array<string, mixed> $map
     */
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
