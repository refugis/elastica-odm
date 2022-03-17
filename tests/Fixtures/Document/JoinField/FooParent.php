<?php declare(strict_types=1);

namespace Tests\Fixtures\Document\JoinField;

use Refugis\ODM\Elastica\Annotation\Analyzer;
use Refugis\ODM\Elastica\Annotation\Document;
use Refugis\ODM\Elastica\Annotation\DocumentId;
use Refugis\ODM\Elastica\Annotation\Field;
use Refugis\ODM\Elastica\Annotation\Filter;
use Refugis\ODM\Elastica\Annotation\Index;
use Refugis\ODM\Elastica\Annotation\IndexName;
use Refugis\ODM\Elastica\Annotation\ParentDocument;
use Refugis\ODM\Elastica\Annotation\PrimaryTerm;
use Refugis\ODM\Elastica\Annotation\SequenceNumber;
use Refugis\ODM\Elastica\Annotation\Tokenizer;

/**
 * @Document(joinType="parent")
 */
class FooParent extends FooGrandParent
{
    /**
     * @var string
     *
     * @DocumentId(strategy="none")
     */
    public $id;

    /**
     * @ParentDocument()
     */
    public ?FooGrandParent $fooGrandParent = null;
}
