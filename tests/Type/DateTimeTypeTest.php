<?php declare(strict_types=1);

namespace Tests\Type;

use Refugis\ODM\Elastica\Type\DateTimeType;
use Refugis\ODM\Elastica\Type\TypeInterface;

class DateTimeTypeTest extends AbstractDateTimeTypeTest
{
    public function getType(): TypeInterface
    {
        return new DateTimeType();
    }

    public function getExpectedClass(): string
    {
        return \DateTime::class;
    }
}
