<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

use Refugis\ODM\Elastica\Completion;
use Refugis\ODM\Elastica\Exception\ConversionFailedException;

use function array_filter;
use function is_array;
use function is_string;

final class CompletionType extends AbstractType
{
    public const NAME = 'completion';

    /**
     * {@inheritdoc}
     */
    public function toPHP($value, array $options = []): ?Completion
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) && isset($value['input'])) {
            $completion = new Completion();
            $completion->input = $value['input'];
            $completion->weight = $value['weight'] ?? null;

            return $completion;
        }

        if (! is_string($value)) {
            throw new ConversionFailedException($value, Completion::class);
        }

        $completion = new Completion();
        $completion->input = $value;

        return $completion;
    }

    /**
     * {@inheritdoc}
     */
    public function toDatabase($value, array $options = []): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Completion) {
            throw new ConversionFailedException($value, Completion::class);
        }

        return $value->toArray();
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
        return array_filter([
            'type' => 'completion',
            'analyzer' => $options['analyzer'] ?? null,
            'search_analyzer' => $options['search_analyzer'] ?? null,
            'preserve_separators' => $options['preserve_separators'] ?? null,
            'preserve_position_increments' => $options['preserve_position_increments'] ?? null,
        ], static function ($value) {
            return $value !== null;
        });
    }
}
