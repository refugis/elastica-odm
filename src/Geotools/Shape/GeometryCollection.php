<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Geotools\Shape;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Represents a geometry collection geo_shape.
 */
final class GeometryCollection extends Geoshape
{
    /** @var Collection<Geoshape> */
    private Collection $geometries;

    public function __construct(Geoshape ...$geoshapes)
    {
        $this->geometries = new ArrayCollection($geoshapes);
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'type' => 'geometrycollection',
            'geometries' => $this->geometries->map(static function (Geoshape $geoshape) {
                return $geoshape->toArray();
            })->toArray(),
        ];
    }
}
