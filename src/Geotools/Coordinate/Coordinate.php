<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Geotools\Coordinate;

use InvalidArgumentException;

use function count;
use function fmod;
use function is_array;
use function is_string;
use function max;
use function min;
use function preg_match;
use function strtoupper;

class Coordinate implements CoordinateInterface
{
    /**
     * The latitude of the coordinate.
     */
    private float $latitude;

    /**
     * The longitude of the coordinate.
     */
    private float $longitude;

    /**
     * Set the latitude and the longitude of the coordinates into an selected ellipsoid.
     *
     * @param float[]|string $coordinates the coordinates
     * @phpstan-param array{0: float, 1: float}|string $coordinates
     *
     * @throws InvalidArgumentException
     */
    public function __construct($coordinates)
    {
        if (is_array($coordinates) && count($coordinates) === 2) {
            $this->setLatitude($coordinates[0]);
            $this->setLongitude($coordinates[1]);
        } elseif (is_string($coordinates)) {
            $this->setFromString($coordinates);
        } else {
            throw new InvalidArgumentException('It should be a string or an array');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function compareTo($other): int
    {
        if (! $other instanceof CoordinateInterface) {
            return -1;
        }

        return $other->getLatitude() <=> $this->latitude ?: $other->getLongitude() <=> $this->longitude;
    }

    /**
     * Creates a new coordinate object.
     *
     * @param float[]|string $coordinates the coordinates
     * @phpstan-param array{0: float, 1: float}|string $coordinates
     */
    public static function create($coordinates): self
    {
        return new self($coordinates);
    }

    public function normalizeLatitude(float $latitude): float
    {
        return (float) max(-90, min(90, $latitude));
    }

    public function normalizeLongitude(float $longitude): float
    {
        $x = (int) $longitude;
        $n = $longitude - (float) $x;
        if ($n !== 0.0 && $x % 360 === 180) {
            return 180.0;
        }

        $mod = fmod($longitude, 360);
        $longitude = $mod < -180 ? $mod + 360 : ($mod > 180 ? $mod - 360 : $mod);

        return (float) $longitude;
    }

    public function setLatitude(float $latitude): void
    {
        $this->latitude = $this->normalizeLatitude($latitude);
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function setLongitude(float $longitude): void
    {
        $this->longitude = $this->normalizeLongitude($longitude);
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    /**
     * Creates a valid and acceptable geographic coordinates.
     *
     * @throws InvalidArgumentException
     */
    public function setFromString(string $coordinates): void
    {
        $inDecimalDegree = $this->toDecimalDegrees($coordinates);
        $this->setLatitude((float) $inDecimalDegree[0]);
        $this->setLongitude((float) $inDecimalDegree[1]);
    }

    /**
     * Converts a valid and acceptable geographic coordinates to decimal degrees coordinate.
     *
     * @see http://en.wikipedia.org/wiki/Geographic_coordinate_conversion
     *
     * @param string $coordinates a valid and acceptable geographic coordinates
     *
     * @return string[] an array of coordinate in decimal degree
     *
     * @throws InvalidArgumentException
     */
    private function toDecimalDegrees(string $coordinates): array
    {
        // 40.446195, -79.948862
        if (preg_match('/(-?\d{1,2}\.?\d*)[, ] ?(-?\d{1,3}\.?\d*)$/', $coordinates, $match)) {
            return [(float) $match[1], (float) $match[2]];
        }

        // 40° 26.7717, -79° 56.93172
        if (
            preg_match(
                '/(-?\d{1,2})\D+(\d{1,2}\.?\d*)[, ] ?(-?\d{1,3})\D+(\d{1,2}\.?\d*)$/',
                $coordinates,
                $match,
            )
        ) {
            return [
                $match[1] + $match[2] / 60,
                $match[3] < 0
                    ? $match[3] - $match[4] / 60
                    : $match[3] + $match[4] / 60,
            ];
        }

        // 40.446195N 79.948862W
        if (preg_match('/(\d{1,2}\.?\d*)\D*([ns])[, ] ?(\d{1,3}\.?\d*)\D*([we])$/i', $coordinates, $match)) {
            return [
                (float) (strtoupper($match[2]) === 'N' ? $match[1] : -$match[1]),
                (float) (strtoupper($match[4]) === 'E' ? $match[3] : -$match[3]),
            ];
        }

        // 40°26.7717S 79°56.93172E
        // 25°59.86′N,21°09.81′W
        if (
            preg_match(
                '/(\d{1,2})\D+(\d{1,2}\.?\d*)\D*([ns])[, ] ?(\d{1,3})\D+(\d{1,2}\.?\d*)\D*([we])$/i',
                $coordinates,
                $match,
            )
        ) {
            $latitude = $match[1] + $match[2] / 60;
            $longitude = $match[4] + $match[5] / 60;

            return [
                (float) (strtoupper($match[3]) === 'N' ? $latitude : -$latitude),
                (float) (strtoupper($match[6]) === 'E' ? $longitude : -$longitude),
            ];
        }

        // 40:26:46N, 079:56:55W
        // 40:26:46.302N 079:56:55.903W
        // 40°26′47″N 079°58′36″W
        // 40d 26′ 47″ N 079d 58′ 36″ W
        if (
            preg_match(
                '/(\d{1,2})\D+(\d{1,2})\D+(\d{1,2}\.?\d*)\D*([ns])[, ] ?(\d{1,3})\D+(\d{1,2})\D+(\d{1,2}\.?\d*)\D*([we])$/i',
                $coordinates,
                $match,
            )
        ) {
            $latitude = $match[1] + ($match[2] * 60 + $match[3]) / 3600;
            $longitude = $match[5] + ($match[6] * 60 + $match[7]) / 3600;

            return [
                (float) (strtoupper($match[4]) === 'N' ? $latitude : -$latitude),
                (float) (strtoupper($match[8]) === 'E' ? $longitude : -$longitude),
            ];
        }

        throw new InvalidArgumentException('It should be a valid and acceptable ways to write geographic coordinates !');
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return [$this->longitude, $this->latitude];
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'lat' => $this->latitude,
            'lon' => $this->longitude,
        ];
    }
}
