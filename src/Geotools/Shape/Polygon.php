<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Geotools\Shape;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Refugis\ODM\Elastica\Geotools\Coordinate\CoordinateInterface;

use function array_map;

/**
 * Represents a polygon geo_shape.
 */
final class Polygon extends Geoshape
{
    /** @var Collection<CoordinateInterface> */
    private Collection $outer;

    /** @var Collection<CoordinateInterface[]> */
    private Collection $holes;

    /**
     * @param CoordinateInterface[][] ...$holes
     */
    public function __construct(array $outer, array ...$holes)
    {
        $normalize = static function (CoordinateInterface ...$coordinate) {
            return $coordinate;
        };

        $this->outer = new ArrayCollection($normalize(...$outer));
        $this->holes = array_map(static fn (array $hole) => new ArrayCollection($normalize(...$hole)), $holes);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        $serialize = static function (CoordinateInterface $coordinate) {
            return $coordinate->jsonSerialize();
        };

        $coordinates = [$this->outer->map($serialize)->toArray()];
        foreach ($this->holes as $hole) {
            $coordinates[] = $hole->map($serialize)->toArray();
        }

        return [
            'type' => 'polygon',
            'coordinates' => $coordinates,
        ];
    }
}
