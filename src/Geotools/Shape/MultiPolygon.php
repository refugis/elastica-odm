<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Geotools\Shape;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Represents a multipolygon geo_shape.
 */
final class MultiPolygon extends Geoshape
{
    /** @var Collection<Polygon> */
    private Collection $polygons;

    public function __construct(Polygon ...$polygons)
    {
        $this->polygons = new ArrayCollection($polygons);
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'type' => 'multipolygon',
            'coordinates' => $this->polygons->map(static function (Polygon $polygon) {
                return $polygon->toArray();
            })->toArray(),
        ];
    }
}
