<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

use InvalidArgumentException;
use Refugis\ODM\Elastica\Exception\ConversionFailedException;

use function is_numeric;

final class IntegerType extends AbstractType
{
    public const NAME = 'integer';

    /**
     * {@inheritdoc}
     */
    public function toPHP($value, array $options = []): ?int
    {
        return $this->doConversion($value);
    }

    /**
     * {@inheritdoc}
     */
    public function toDatabase($value, array $options = []): ?int
    {
        return $this->doConversion($value);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getMappingDeclaration(array $options = []): array
    {
        $length = $options['length'] ?? 4;
        switch ($length) {
            case 1:
                $type = 'byte';
                break;

            case 2:
                $type = 'short';
                break;

            case 4:
                $type = 'integer';
                break;

            case 8:
                $type = 'long';
                break;

            default:
                throw new InvalidArgumentException('Invalid length ' . $length . ' for integer');
        }

        return ['type' => $type];
    }

    /**
     * @param mixed $value
     */
    private function doConversion($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            throw new ConversionFailedException($value, self::NAME);
        }

        return (int) $value;
    }
}
