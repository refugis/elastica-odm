<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Geotools\Shape;

use Refugis\ODM\Elastica\Geotools\Coordinate\CoordinateInterface;

/**
 * Represents a circle geo_shape.
 */
final class Circle extends Geoshape
{
    private CoordinateInterface $center;
    private string $radius;

    public function __construct(CoordinateInterface $center, string $radius)
    {
        $this->center = $center;
        $this->radius = $radius;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'type' => 'linestring',
            'coordinates' => $this->center->jsonSerialize(),
            'radius' => $this->radius,
        ];
    }
}
