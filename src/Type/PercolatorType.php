<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

use Elastica\Query;
use Refugis\ODM\Elastica\Exception\ConversionFailedException;

use function is_array;

final class PercolatorType extends AbstractType
{
    public const NAME = 'percolator';

    /**
     * {@inheritDoc}
     */
    public function toPHP($value, array $options = []): ?Query
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value) && ! $value instanceof Query) {
            throw new ConversionFailedException($value, 'array');
        }

        return Query::create(['query' => $value]);
    }

    /**
     * {@inheritDoc}
     */
    public function toDatabase($value, array $options = []): ?array
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof Query) {
            $value = $value->getQuery();
        }

        if ($value instanceof Query\AbstractQuery) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return $value;
        }

        throw new ConversionFailedException($value, [Query::class, Query\AbstractQuery::class, 'array']);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * {@inheritDoc}
     */
    public function getMappingDeclaration(array $options = []): array
    {
        return ['type' => 'percolator'];
    }
}
