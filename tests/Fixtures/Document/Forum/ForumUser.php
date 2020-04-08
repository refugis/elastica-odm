<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Fixtures\Document\Forum;

use Refugis\ODM\Elastica\Annotation\Document;
use Refugis\ODM\Elastica\Annotation\DocumentId;
use Refugis\ODM\Elastica\Annotation\Field;

/**
 * @Document()
 */
class ForumUser
{
    /**
     * @DocumentId()
     */
    public $id;

    /**
     * @Field(type="string", options={"analyzed": false})
     */
    public $username;

    public function getId(): string
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }
}
