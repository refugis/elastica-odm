<?php declare(strict_types=1);

namespace Tests\Fixtures\Document;

use Refugis\ODM\Elastica\Annotation\Document;
use Refugis\ODM\Elastica\Annotation\DocumentId;
use Refugis\ODM\Elastica\Annotation\Field;
use Refugis\ODM\Elastica\Annotation\Version;

/**
 * @Document(collection="foo_with_version_index")
 */
class FooWithVersion
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
     * @Version(type=Version::EXTERNAL)
     */
    public ?int $version = null;
}
