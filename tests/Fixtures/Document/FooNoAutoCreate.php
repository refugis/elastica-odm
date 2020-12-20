<?php declare(strict_types=1);

namespace Tests\Fixtures\Document;

use Refugis\ODM\Elastica\Annotation\Analyzer;
use Refugis\ODM\Elastica\Annotation\Document;
use Refugis\ODM\Elastica\Annotation\DocumentId;
use Refugis\ODM\Elastica\Annotation\Field;
use Refugis\ODM\Elastica\Annotation\Filter;
use Refugis\ODM\Elastica\Annotation\Index;
use Refugis\ODM\Elastica\Annotation\Tokenizer;
use Refugis\ODM\Elastica\Geotools\Coordinate\CoordinateInterface;

/**
 * @Document(collection="foo_index_no_auto_create")
 * @Index(analyzers={
 *     @Analyzer(name="foo_analyzer", tokenizer="foo_tokenizer", charFilters={"html_strip"}, filters={"lowercase", "english_stop", "english_stemmer"})
 * }, tokenizers={
 *     @Tokenizer(name="foo_tokenizer", type="edge_ngram", options={"min_gram": 3, "max_gram": 15, "token_chars": {"letter", "digit"}})
 * }, filters={
 *     @Filter(name="english_stop", type="stop", options={"stopwords": "_english_"}),
 *     @Filter(name="english_stemmer", type="stemmer", options={"language": "english"}),
 * })
 */
class FooNoAutoCreate
{
    /**
     * @var string
     *
     * @DocumentId(strategy="none")
     */
    public $id;

    /**
     * @var string
     *
     * @Field(type="string")
     */
    public $stringField;

    /**
     * @var CoordinateInterface
     *
     * @Field(type="geo_point")
     */
    public $coordinates;
}
