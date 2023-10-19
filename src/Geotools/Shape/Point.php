<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Geotools\Shape;

use Refugis\ODM\Elastica\Geotools\Coordinate\CoordinateInterface;

/**
 * Represents a point geo_shape.
 */
final class Point extends Geoshape
{
    private CoordinateInterface $coordinate;

    public function __construct(CoordinateInterface $coordinate)
    {
        $this->coordinate = $coordinate;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'type' => 'point',
            'coordinates' => $this->coordinate->jsonSerialize(),
        ];
    }
}
