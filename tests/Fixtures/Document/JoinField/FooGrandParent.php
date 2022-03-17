<?php declare(strict_types=1);

namespace Tests\Fixtures\Document\JoinField;

use Refugis\ODM\Elastica\Annotation\DiscriminatorMap;
use Refugis\ODM\Elastica\Annotation\Document;
use Refugis\ODM\Elastica\Annotation\DocumentId;
use Refugis\ODM\Elastica\Annotation\IndexName;
use Refugis\ODM\Elastica\Annotation\InheritanceType;
use Refugis\ODM\Elastica\Annotation\JoinRelationsMap;
use Refugis\ODM\Elastica\Annotation\PrimaryTerm;
use Refugis\ODM\Elastica\Annotation\SequenceNumber;

/**
 * @Document(collection="foo_join_index")
 * @InheritanceType(InheritanceType::PARENT_CHILD)
 * @DiscriminatorMap({
 *  "grandparent": FooGrandParent::class,
 *  "parent": FooParent::class,
 *  "child": FooChild::class,
 * })
 * @JoinRelationsMap({
 *  FooGrandParent::class: { FooParent::class: { FooChild::class: {} } }
 * })
 */
class FooGrandParent
{
    /**
     * @var string
     *
     * @DocumentId(strategy="none")
     */
    public $id;

    /**
     * @IndexName()
     */
    public $indexName;

    /**
     * @SequenceNumber()
     */
    public $seqNo;

    /**
     * @PrimaryTerm()
     */
    public $primaryTerm;
}
