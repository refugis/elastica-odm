<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

use InvalidArgumentException;
use Refugis\ODM\Elastica\Exception\ConversionFailedException;

use function is_float;

final class FloatType extends AbstractType
{
    public const NAME = 'float';

    /**
     * {@inheritDoc}
     */
    public function toPHP($value, array $options = []): ?float
    {
        if ($value === null) {
            return null;
        }

        return (float) $value;
    }

    /**
     * {@inheritDoc}
     */
    public function toDatabase($value, array $options = []): ?float
    {
        if ($value === null) {
            return null;
        }

        if (! is_float($value)) {
            throw new ConversionFailedException($value, 'float');
        }

        return $value;
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
        switch ($options['length'] ?? 4) {
            case 4:
                $type = 'float';
                break;

            case 8:
                $type = 'double';
                break;

            default:
                throw new InvalidArgumentException('Invalid length for float field');
        }

        return ['type' => $type];
    }
}
