<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

use function base64_encode;
use function Safe\base64_decode;

final class BinaryType extends AbstractType
{
    public const NAME = 'binary';

    /**
     * {@inheritDoc}
     */
    public function toPHP($value, array $options = []): ?string
    {
        if (empty($value)) {
            return null;
        }

        return base64_decode($value, true);
    }

    /**
     * {@inheritDoc}
     */
    public function toDatabase($value, array $options = []): ?string
    {
        if (empty($value)) {
            return null;
        }

        return base64_encode($value);
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
        return ['type' => 'binary'];
    }
}
