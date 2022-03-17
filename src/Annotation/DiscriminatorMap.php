<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;
use Kcs\Metadata\Exception\InvalidMetadataException;

use function Safe\sprintf;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class DiscriminatorMap
{
    /**
     * The discriminator map.
     *
     * @Required()
     * @var array<string, class-string>
     */
    public array $map;

    /**
     * @param array<string, class-string> $map
     */
    public function __construct(array $map)
    {
        $data = $map;
        if (! isset($map['map']) && ! isset($map['value'])) {
            $data = ['map' => $map];
        }

        $this->map = $data['map'] ?? $data['value'] ?? null;

        if (empty($this->map)) {
            throw new InvalidMetadataException('Invalid discriminator map: must be not empty.');
        }

        $seenClasses = [];
        foreach ($this->map as $class) {
            if (isset($seenClasses[$class])) {
                throw new InvalidMetadataException(sprintf('Invalid discriminator map: class "%s" is duplicated', $class));
            }

            $seenClasses[$class] = true;
        }
    }
}
