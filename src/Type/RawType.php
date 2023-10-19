<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

// TODO: remove me!
final class RawType extends AbstractType
{
    public const NAME = 'raw';

    /**
     * {@inheritDoc}
     */
    public function toPHP($value, array $options = [])
    {
        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function toDatabase($value, array $options = [])
    {
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
        return [];
    }
}
