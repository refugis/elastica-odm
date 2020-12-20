<?php declare(strict_types=1);

namespace Tests\Type;

use Refugis\ODM\Elastica\Type\DateTimeImmutableType;
use Refugis\ODM\Elastica\Type\TypeInterface;

class DateTimeImmutableTypeTest extends AbstractDateTimeTypeTest
{
    public function getType(): TypeInterface
    {
        return new DateTimeImmutableType();
    }

    public function getExpectedClass(): string
    {
        return \DateTimeImmutable::class;
    }
}
