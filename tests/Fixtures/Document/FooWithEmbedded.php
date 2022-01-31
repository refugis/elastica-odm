<?php declare(strict_types=1);

namespace Tests\Fixtures\Document;

use Refugis\ODM\Elastica\Annotation\Document;
use Refugis\ODM\Elastica\Annotation\DocumentId;
use Refugis\ODM\Elastica\Annotation\Embedded;

/**
 * @Document()
 */
class FooWithEmbedded
{
    /**
     * @var string
     *
     * @DocumentId(strategy="none")
     */
    public $id;

    /**
     * @var FooEmbeddable
     *
     * @Embedded(targetClass=FooEmbeddable::class)
     */
    public $emb;
}
