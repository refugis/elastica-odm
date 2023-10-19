<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Geotools\Shape;

use InvalidArgumentException;
use Refugis\ODM\Elastica\Geotools\Coordinate\Coordinate;

use function array_map;
use function array_shift;

abstract class Geoshape implements GeoshapeInterface
{
    /** @param array<string, mixed> $shape */
    public static function fromArray(array $shape): Geoshape
    {
        $type = $shape['type'] ?? null;

        switch ($type) {
            case 'point':
                return new Point(new Coordinate($shape['coordinates']));

            case 'circle':
                return new Circle(new Coordinate($shape['coordinates']), $shape['radius']);

            case 'linestring':
                return new Linestring(...array_map(Coordinate::class . '::create', $shape['coordinates']));

            case 'polygon':
                return self::createPolygon($shape['coordinates']);

            case 'multipolygon':
                return new MultiPolygon(...array_map(self::class . '::createPolygon', $shape['coordinates']));

            case 'geometrycollection':
                return new GeometryCollection(...array_map(self::class . '::fromArray', $shape['geometries']));

            default:
                throw new InvalidArgumentException('Unknown geoshape type "' . ($type ?? 'null') . '"');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Creates a Polygon object from coordinates array.
     *
     * @param array<array|string> $coordinates
     */
    private static function createPolygon(array $coordinates): Polygon
    {
        $polygon = array_shift($coordinates);

        return new Polygon(array_map([Coordinate::class, 'create'], $polygon), ...array_map(static function (array $poly) {
            return array_map([Coordinate::class, 'create'], $poly);
        }, $coordinates));
    }
}
