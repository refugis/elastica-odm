<?php declare(strict_types=1);

namespace Tests\Fixtures\Document;

use Refugis\ODM\Elastica\Annotation\Embeddable;
use Refugis\ODM\Elastica\Annotation\Field;

/**
 * @Embeddable()
 */
class FooNestedEmbeddable
{
    /**
     * @var string
     *
     * @Field(type="string")
     */
    public $stringFieldRenest;
}
