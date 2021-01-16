<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;
use TypeError;

use function get_debug_type;
use function is_array;
use function is_string;
use function Safe\sprintf;

/**
 * @Annotation
 * @Target({"ANNOTATION"})
 */
#[Attribute]
final class Analyzer
{
    /**
     * The name of the analyzer.
     *
     * @Required()
     */
    public string $name;

    /**
     * The tokenizer of the analyzer.
     *
     * @Required()
     */
    public string $tokenizer;

    /**
     * Array of char filters name.
     *
     * @var string[]
     */
    public ?array $charFilters = null;

    /**
     * Array of filters.
     *
     * @var string[]
     */
    public ?array $filters = null;

    public function __construct($name, ?string $tokenizer = null, ?array $charFilters = null, ?array $filters = null)
    {
        if (is_string($name)) {
            $data = ['name' => $name];
        } elseif (is_array($name)) {
            $data = $name;
        } else {
            throw new TypeError(sprintf('Argument #1 passed to %s must be a string. %s passed', __METHOD__, get_debug_type($name)));
        }

        $this->name = $data['name'] ?? null;
        $this->tokenizer = $data['tokenizer'] ?? $tokenizer;
        $this->charFilters = $data['charFilters'] ?? $charFilters;
        $this->filters = $data['filters'] ?? $filters;

        if ($this->tokenizer === null) {
            throw new TypeError(sprintf('Argument #2 passed to %s must be a string. %s passed', __METHOD__, get_debug_type($tokenizer)));
        }
    }
}
