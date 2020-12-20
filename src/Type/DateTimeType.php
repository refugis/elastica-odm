<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

use DateTime;

final class DateTimeType extends AbstractDateTimeType
{
    public const NAME = 'datetime';

    public function getName(): string
    {
        return self::NAME;
    }

    protected function getClass(): string
    {
        return DateTime::class;
    }
}
