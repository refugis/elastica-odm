<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Geotools\Coordinate;

use Doctrine\Common\Comparable;
use Elastica\ArrayableInterface;
use JsonSerializable;

interface CoordinateInterface extends ArrayableInterface, Comparable, JsonSerializable
{
    /**
     * Normalizes a latitude to the (-90, 90) range.
     * Latitudes below -90.0 or above 90.0 degrees are capped, not wrapped.
     *
     * @param float $latitude The latitude to normalize
     */
    public function normalizeLatitude(float $latitude): float;

    /**
     * Normalizes a longitude to the (-180, 180) range.
     * Longitudes below -180.0 or abode 180.0 degrees are wrapped.
     *
     * @param float $longitude The longitude to normalize
     */
    public function normalizeLongitude(float $longitude): float;

    /**
     * Set the latitude.
     */
    public function setLatitude(float $latitude): void;

    /**
     * Get the latitude.
     */
    public function getLatitude(): float;

    /**
     * Set the longitude.
     */
    public function setLongitude(float $longitude): void;

    /**
     * Get the longitude.
     */
    public function getLongitude(): float;
}
