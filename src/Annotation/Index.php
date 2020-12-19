<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Doctrine\Common\Annotations\Annotation\Target;
use function Safe\sprintf;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
final class Index
{
    /**
     * The filters of this index.
     *
     * @var \Refugis\ODM\Elastica\Annotation\Filter[]
     */
    public array $filters;

    /**
     * The analyzers of this index.
     *
     * @var \Refugis\ODM\Elastica\Annotation\Analyzer[]
     */
    public array $analyzers;

    /**
     * The tokenizers of this index.
     *
     * @var \Refugis\ODM\Elastica\Annotation\Tokenizer[]
     */
    public array $tokenizers;

    public function __construct(array $filters = [], array $analyzers = [], array $tokenizers = [])
    {
        if (isset($filters['filters']) || isset($filters['analyzers']) || isset($filters['tokenizers'])) {
            $data = $filters;
        } else {
            $data = [ 'filters' => $filters ];
        }

        $this->filters = $data['filters'] ?? [];
        $this->analyzers = $data['analyzers'] ?? $analyzers;
        $this->tokenizers = $data['tokenizers'] ?? $tokenizers;
    }
}
