<?php

declare(strict_types=1);

namespace Tests\Type\Functional;

use PHPUnit\Framework\TestCase;
use Refugis\ODM\Elastica\Repository\DocumentRepository;
use Tests\Fixtures\Document\DateTime\Foo;
use Tests\Traits\DocumentManagerTestTrait;
use Tests\Traits\FixturesTestTrait;

class DateTimeTypeTest extends TestCase
{
    use DocumentManagerTestTrait;
    use FixturesTestTrait;

    public static function setUpBeforeClass(): void
    {
        self::resetFixtures(self::createDocumentManager());
    }

    public function testDateTimePersistence(): void
    {
        $doc = new Foo();
        $doc->id = __METHOD__;
        $doc->dateTime = new \DateTime('2022-03-20T10:11:00Z');
        $doc->dateTimeImmutable = $doc->timestamp = new \DateTimeImmutable('2022-03-20T10:11:00Z');
        $doc->customFormat = new \DateTimeImmutable('2022-03-20T11:11:00 Europe/Rome');

        $dm = self::createDocumentManager();
        $dm->persist($doc);
        $dm->flush();
        $dm->clear();

        $doc = $dm->find(Foo::class, __METHOD__);
        self::assertEquals(new \DateTime('2022-03-20T10:11:00Z'), $doc->dateTime);
        self::assertEquals(new \DateTimeImmutable('2022-03-20T10:11:00Z'), $doc->dateTimeImmutable);
        self::assertEquals(new \DateTimeImmutable('2022-03-20T10:11:00Z'), $doc->timestamp);
        self::assertEquals(new \DateTimeImmutable('2022-03-20T11:11:00 Europe/Rome'), $doc->customFormat);
    }
}
