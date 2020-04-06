<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Geotools\Shape;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Refugis\ODM\Elastica\Geotools\Coordinate\CoordinateInterface;

/**
 * Represents a linestring geo_shape.
 */
final class Linestring extends Geoshape
{
    /**
     * @var Collection|CoordinateInterface[]
     */
    private $coordinates;

    public function __construct(CoordinateInterface ...$coordinates)
    {
        $this->coordinates = new ArrayCollection($coordinates);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'type' => 'linestring',
            'coordinates' => $this->coordinates->map(static function (CoordinateInterface $coordinate) {
                return $coordinate->jsonSerialize();
            })->toArray(),
        ];
    }
}
