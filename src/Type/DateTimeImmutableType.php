<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

use DateTimeImmutable;

final class DateTimeImmutableType extends AbstractDateTimeType
{
    public const NAME = 'datetime_immutable';

    public function getName(): string
    {
        return self::NAME;
    }

    protected function getClass(): string
    {
        return DateTimeImmutable::class;
    }
}
