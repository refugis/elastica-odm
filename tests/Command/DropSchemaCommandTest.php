<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Command;

use PHPUnit\Framework\TestCase;
use Refugis\ODM\Elastica\Command\DropSchemaCommand;
use Refugis\ODM\Elastica\Tests\Traits\DocumentManagerTestTrait;
use Refugis\ODM\Elastica\Tests\Traits\FixturesTestTrait;
use Symfony\Component\Console\Tester\CommandTester;

class DropSchemaCommandTest extends TestCase
{
    use DocumentManagerTestTrait;
    use FixturesTestTrait;

    public function testShouldDropIndexesSuccessfully(): void
    {
        self::resetFixtures($dm = self::createDocumentManager());
        if (\version_compare($dm->getDatabase()->getConnection()->getVersion(), '6.0.0', '<')) {
            self::markTestSkipped('Deletion of aliases is rejected only from ES 6.0');
        }

        $tester = new CommandTester(new DropSchemaCommand($dm));
        $collectionName = 'foo_with_aliases_index' . (\version_compare($dm->getDatabase()->getConnection()->getVersion(), '7.0.0', '>=') ? '' : '/foo_with_aliases_index');

        $tester->execute([], ['interactive' => false]);
        self::assertEquals(<<<CMDLINE

Elastica ODM - drop schema
==========================

 ! [CAUTION] This operation will drop all the indices defined in your mapping.

 [WARNING] $collectionName is an alias.

           Pass --with-aliases option to drop the alias too.

 [OK] All done.


CMDLINE
, \implode("\n", \array_map('rtrim', \explode("\n", $tester->getDisplay(true)))));
    }

    public function testShouldDropIndexesAndAliasesSuccessfully(): void
    {
        self::resetFixtures($dm = self::createDocumentManager());
        $tester = new CommandTester(new DropSchemaCommand($dm));

        $tester->execute(['--with-aliases' => true], ['interactive' => false]);
        self::assertEquals(<<<CMDLINE

Elastica ODM - drop schema
==========================

 ! [CAUTION] This operation will drop all the indices defined in your mapping.

 [OK] All done.


CMDLINE
, \implode("\n", \array_map('rtrim', \explode("\n", $tester->getDisplay(true)))));
    }
}
