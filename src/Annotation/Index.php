<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Index
{
    /**
     * The filters of this index.
     *
     * @var Filter[]
     */
    public array $filters;

    /**
     * The analyzers of this index.
     *
     * @var Analyzer[]
     */
    public array $analyzers;

    /**
     * The tokenizers of this index.
     *
     * @var Tokenizer[]
     */
    public array $tokenizers;

    public function __construct(array $filters = [], array $analyzers = [], array $tokenizers = [])
    {
        if (isset($filters['filters']) || isset($filters['analyzers']) || isset($filters['tokenizers'])) {
            $data = $filters;
        } else {
            $data = ['filters' => $filters];
        }

        $this->filters = $data['filters'] ?? [];
        $this->analyzers = $data['analyzers'] ?? $analyzers;
        $this->tokenizers = $data['tokenizers'] ?? $tokenizers;
    }
}
