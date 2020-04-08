<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Fixtures\Document\CMS;

use Refugis\ODM\Elastica\Annotation\Document;
use Refugis\ODM\Elastica\Annotation\DocumentId;
use Refugis\ODM\Elastica\Annotation\Field;

/**
 * @Document()
 */
class CmsPhoneNumber
{
    /**
     * @DocumentId()
     * @Field(type="string")
     */
    public $phonenumber;
}
