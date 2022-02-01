<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

use Refugis\ODM\Elastica\Exception\ConversionFailedException;

use function is_array;

class FlattenedType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function toPHP($value, array $options = [])
    {
        if (null === $value) {
            return null;
        }

        if (!is_array($value)) {
            throw new ConversionFailedException($value, 'array');
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function toDatabase($value, array $options = [])
    {
        if (null === $value) {
            return null;
        }

        if (!is_array($value)) {
            throw new ConversionFailedException($value, 'array');
        }

        return $value;
    }

    public function getName(): string
    {
        return 'flattened';
    }

    /**
     * {@inheritdoc}
     */
    public function getMappingDeclaration(array $options = []): array
    {
        return ['type' => 'flattened'];
    }
}
