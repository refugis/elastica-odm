<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica;

use Elastica\ArrayableInterface;

use function array_filter;

final class Completion implements ArrayableInterface
{
    /** @var string|string[] */
    public $input;
    public ?int $weight = null;

    /** @return array<string, mixed>|null */
    public function toArray(): ?array
    {
        return array_filter([
            'input' => $this->input,
            'weight' => $this->weight,
        ]) ?: null;
    }
}
