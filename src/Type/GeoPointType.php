<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

use Refugis\ODM\Elastica\Exception\ConversionFailedException;
use Refugis\ODM\Elastica\Geotools\Coordinate\Coordinate;
use Refugis\ODM\Elastica\Geotools\Coordinate\CoordinateInterface;
use Refugis\ODM\Elastica\Geotools\Geohash\Geohash;

use function is_array;
use function is_string;
use function strpos;

final class GeoPointType extends AbstractType
{
    public const NAME = 'geo_point';

    /**
     * {@inheritdoc}
     */
    public function toPHP($value, array $options = []): ?CoordinateInterface
    {
        if (empty($value)) {
            return null;
        }

        if (is_array($value)) {
            if (isset($value['lat'], $value['lon'])) {
                $lat = $value['lat'];
                $lon = $value['lon'];
            } else {
                [$lon, $lat] = $value;
            }

            return new Coordinate([(float) $lat, (float) $lon]);
        }

        if (is_string($value)) {
            if (strpos($value, ',') === false) {
                return (new Geohash($value))->getCoordinate();
            }

            return new Coordinate($value);
        }

        throw new ConversionFailedException($value, 'geo point');
    }

    /**
     * {@inheritdoc}
     */
    public function toDatabase($value, array $options = []): ?array
    {
        if (empty($value)) {
            return null;
        }

        if (! $value instanceof CoordinateInterface) {
            throw new ConversionFailedException($value, CoordinateInterface::class);
        }

        return $value->toArray();
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getMappingDeclaration(array $options = []): array
    {
        return ['type' => 'geo_point'];
    }
}
