<?php declare(strict_types=1);

namespace Tests\Fixtures\Document\DateTime;

use DateTime;
use DateTimeImmutable;
use Refugis\ODM\Elastica\Annotation\Document;
use Refugis\ODM\Elastica\Annotation\DocumentId;
use Refugis\ODM\Elastica\Annotation\Field;

/**
 * @Document(collection="foo_datetime")
 */
class Foo
{
    /**
     * @var string
     *
     * @DocumentId(strategy="none")
     */
    public $id;

    /**
     * @Field(type="datetime")
     */
    public DateTime $dateTime;

    /**
     * @Field(type="datetime_immutable")
     */
    public DateTimeImmutable $dateTimeImmutable;

    /**
     * @Field(type="datetime_immutable", options={"format": "d/M/Y H:i:s e"})
     */
    public DateTimeImmutable $customFormat;

    /**
     * @Field(type="datetime_immutable", options={"format": "U"})
     */
    public DateTimeImmutable $timestamp;
}
