<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

final class IpType extends AbstractType
{
    public const NAME = 'ip';

    /**
     * {@inheritDoc}
     */
    public function toPHP($value, array $options = []): ?string
    {
        if (empty($value)) {
            return null;
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function toDatabase($value, array $options = []): ?string
    {
        if (empty($value)) {
            return null;
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
        return ['type' => 'ip'];
    }
}
