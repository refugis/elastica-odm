<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

use Refugis\ODM\Elastica\Exception\ConversionFailedException;

use function is_array;

class FlattenedType extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function toPHP($value, array $options = [])
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new ConversionFailedException($value, 'array');
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function toDatabase($value, array $options = [])
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new ConversionFailedException($value, 'array');
        }

        return $value;
    }

    public function getName(): string
    {
        return 'flattened';
    }

    /**
     * {@inheritDoc}
     */
    public function getMappingDeclaration(array $options = []): array
    {
        return ['type' => 'flattened'];
    }
}
