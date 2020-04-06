<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

final class DateTimeImmutableType extends AbstractDateTimeType
{
    public const NAME = 'datetime_immutable';

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    protected function getClass(): string
    {
        return \DateTimeImmutable::class;
    }
}
