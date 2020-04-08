<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Fixtures\Document\GeoNames;

use Refugis\ODM\Elastica\Annotation\Document;
use Refugis\ODM\Elastica\Annotation\DocumentId;
use Refugis\ODM\Elastica\Annotation\Field;

/**
 * @Document("geonames_city")
 */
class City
{
    /**
     * @DocumentId(strategy="none")
     */
    public $id;

    /**
     * @Field(type="string", options={"analyzed": false});
     */
    public $name;

    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
