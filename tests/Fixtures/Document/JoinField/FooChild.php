<?php declare(strict_types=1);

namespace Tests\Fixtures\Document\JoinField;

use Refugis\ODM\Elastica\Annotation\Document;
use Refugis\ODM\Elastica\Annotation\DocumentId;
use Refugis\ODM\Elastica\Annotation\ParentDocument;

/**
 * @Document(joinType="child")
 */
class FooChild extends FooGrandParent
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
    public ?FooParent $fooParent = null;
}
