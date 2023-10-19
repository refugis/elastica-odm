<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

use Refugis\ODM\Elastica\Exception\ConversionFailedException;
use Refugis\ODM\Elastica\Geotools\Shape\Geoshape;

final class GeoShapeType extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function toPHP($value, array $options = []): ?Geoshape
    {
        if (empty($value)) {
            return null;
        }

        return Geoshape::fromArray($value);
    }

    /**
     * {@inheritDoc}
     */
    public function toDatabase($value, array $options = []): ?array
    {
        if (empty($value)) {
            return null;
        }

        if (! $value instanceof Geoshape) {
            throw new ConversionFailedException($value, Geoshape::class);
        }

        return $value->toArray();
    }

    public function getName(): string
    {
        return 'geo_shape';
    }

    /**
     * {@inheritDoc}
     */
    public function getMappingDeclaration(array $options = []): array
    {
        return ['type' => 'geo_shape'];
    }
}
