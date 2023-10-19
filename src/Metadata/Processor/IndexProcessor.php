<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\Index;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

use function array_filter;
use function array_merge;

/** @Processor(annotation=Index::class) */
class IndexProcessor implements ProcessorInterface
{
    /**
     * {@inheritDoc}
     *
     * @param DocumentMetadata $metadata
     * @param Index            $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $analysis = [
            'filter' => [],
            'tokenizer' => [],
            'analyzer' => [],
        ];

        foreach ($subject->filters ?? [] as $filter) {
            $setting = ['type' => $filter->type];
            $analysis['filter'][$filter->name] = array_merge($setting, $filter->options);
        }

        foreach ($subject->tokenizers ?? [] as $tokenizer) {
            $setting = ['type' => $tokenizer->type];
            $analysis['tokenizer'][$tokenizer->name] = array_merge($setting, $tokenizer->options);
        }

        foreach ($subject->analyzers ?? [] as $analyzer) {
            $analysis['analyzer'][$analyzer->name] = array_filter([
                'tokenizer' => $analyzer->tokenizer,
                'char_filter' => $analyzer->charFilters,
                'filter' => $analyzer->filters,
            ]);
        }

        $analysis = array_filter($analysis);

        if (empty($analysis)) {
            return;
        }

        $metadata->staticSettings['analysis'] = $analysis;
    }
}
