<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

use Refugis\ODM\Elastica\Exception\ConversionFailedException;

use function is_bool;

final class BooleanType extends AbstractType
{
    public const NAME = 'boolean';

    /**
     * {@inheritDoc}
     */
    public function toPHP($value, array $options = []): ?bool
    {
        return $this->doConversion($value);
    }

    /**
     * {@inheritDoc}
     */
    public function toDatabase($value, array $options = []): ?bool
    {
        return $this->doConversion($value);
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
        return ['type' => 'boolean'];
    }

    /** @param mixed $value */
    private function doConversion($value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (! is_bool($value)) {
            throw new ConversionFailedException($value, 'bool');
        }

        return $value;
    }
}
