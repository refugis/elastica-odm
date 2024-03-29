<?php declare(strict_types=1);

namespace Tests\Metadata\Processor;

use Elastica\Mapping;
use Elastica\Type\Mapping as TypeMapping;
use PHPUnit\Framework\TestCase;
use Refugis\ODM\Elastica\Annotation\Analyzer;
use Refugis\ODM\Elastica\Annotation\Filter;
use Refugis\ODM\Elastica\Annotation\Index;
use Refugis\ODM\Elastica\Annotation\Tokenizer;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Metadata\Processor\IndexProcessor;
use Tests\Fixtures\Document\Foo;
use Tests\Traits\DocumentManagerTestTrait;

class IndexProcessorTest extends TestCase
{
    use DocumentManagerTestTrait;

    private IndexProcessor $processor;
    private DocumentMetadata $documentMetadata;

    protected function setUp(): void
    {
        $this->processor = new IndexProcessor();
        $this->documentMetadata = new DocumentMetadata(new \ReflectionClass(Foo::class));
    }

    public function testAnalyzersAreProcessedCorrectly(): void
    {
        $index = new Index();

        $analyzer = new Analyzer('foo_name', 'foo_tokenizer');
        $index->analyzers = [$analyzer];

        $this->processor->process($this->documentMetadata, $index);

        self::assertEquals([
            'analysis' => [
                'analyzer' => [
                    'foo_name' => [
                        'tokenizer' => 'foo_tokenizer',
                    ],
                ],
            ],
        ], $this->documentMetadata->staticSettings);
    }

    public function testFiltersAreProcessedCorrectly(): void
    {
        $index = new Index();

        $filter = new Filter('foo_name', 'stop', [ 'stopwords' => '_english_' ]);
        $index->filters = [$filter];

        $this->processor->process($this->documentMetadata, $index);

        self::assertEquals([
            'analysis' => [
                'filter' => [
                    'foo_name' => [
                        'type' => 'stop',
                        'stopwords' => '_english_',
                    ],
                ],
            ],
        ], $this->documentMetadata->staticSettings);
    }

    public function testTokenizersAreProcessedCorrectly(): void
    {
        $index = new Index();

        $tokenizer = new Tokenizer('foo_name', 'ngram', ['min_gram' => 3]);
        $index->tokenizers = [$tokenizer];

        $this->processor->process($this->documentMetadata, $index);

        self::assertEquals([
            'analysis' => [
                'tokenizer' => [
                    'foo_name' => [
                        'type' => 'ngram',
                        'min_gram' => 3,
                    ],
                ],
            ],
        ], $this->documentMetadata->staticSettings);
    }

    /**
     * @group functional
     */
    public function testIndexIsCreatedWithCorrectIndexParams(): void
    {
        $dm = static::createDocumentManager();

        $properties = [
            'stringField' => ['type' => 'text'],
        ];

        $mapping = class_exists(TypeMapping::class) ? TypeMapping::create($properties) : Mapping::create($properties);

        $collection = $dm->getCollection(Foo::class);
        $collection->drop();
        $collection->updateMapping($mapping);

        $database = $dm->getDatabase();
        $connection = $database->getConnection();

        $fooIndex = $connection->getIndex('foo_index');
        self::assertArrayHasKey('analysis', $fooIndex->getSettings()->get());
        self::assertEquals([
            'filter' => [
                'english_stemmer' => [
                    'type' => 'stemmer',
                    'language' => 'english',
                ],
                'english_stop' => [
                    'type' => 'stop',
                    'stopwords' => '_english_',
                ],
            ],
            'analyzer' => [
                'foo_analyzer' => [
                    'tokenizer' => 'foo_tokenizer',
                    'char_filter' => [
                        'html_strip',
                    ],
                    'filter' => [
                        'lowercase',
                        'english_stop',
                        'english_stemmer',
                    ],
                ],
            ],
            'tokenizer' => [
                'foo_tokenizer' => [
                    'type' => 'edge_ngram',
                    'min_gram' => 3,
                    'max_gram' => 15,
                    'token_chars' => [
                        'letter',
                        'digit',
                    ],
                ],
            ],
        ], $fooIndex->getSettings()->get('analysis'));

        self::assertNull($fooIndex->getSettings()->get('refresh_interval'));

        $collection->setDynamicSettings(['index.refresh_interval' => '1m']);
        $collection->updateMapping($mapping);
        self::assertEquals('1m', $fooIndex->getSettings()->get('refresh_interval'));
    }
}
