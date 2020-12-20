<?php declare(strict_types=1);

namespace Tests\Fixtures\Document\GeoNames;

use Refugis\ODM\Elastica\Annotation\Document;
use Refugis\ODM\Elastica\Annotation\DocumentId;
use Refugis\ODM\Elastica\Annotation\Field;

/**
 * @Document("geonames_country")
 */
class Country
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
