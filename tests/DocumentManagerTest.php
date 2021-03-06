<?php declare(strict_types=1);

namespace Tests;

use Elastica\Index;
use Elastica\Type;
use PHPUnit\Framework\TestCase;
use ProxyManager\Proxy\ProxyInterface;
use Refugis\ODM\Elastica\DocumentManager;
use Refugis\ODM\Elastica\Geotools\Coordinate\Coordinate;
use Tests\Fixtures\Document\Foo;
use Tests\Fixtures\Document\FooNoAutoCreate;
use Tests\Fixtures\Document\FooWithLazyField;
use Tests\Traits\DocumentManagerTestTrait;
use Tests\Traits\FixturesTestTrait;
use Refugis\ODM\Elastica\VarDumper\VarDumperTestTrait;

/**
 * @group functional
 */
class DocumentManagerTest extends TestCase
{
    use DocumentManagerTestTrait;
    use FixturesTestTrait;
    use VarDumperTestTrait;

    /**
     * @var DocumentManager
     */
    private $dm;

    public static function setUpBeforeClass(): void
    {
        self::resetFixtures(self::createDocumentManager());
    }

    protected function setUp(): void
    {
        $this->dm = self::createDocumentManager();
    }

    public function testFindShouldReturnNullIfNoDocumentIsFound(): void
    {
        self::assertNull($this->dm->find(Foo::class, 'non-existent'));
    }

    public function testFindShouldReturnAnObject(): void
    {
        $result = $this->dm->find(Foo::class, 'foo_test_document');
        self::assertInstanceOf(Foo::class, $result);

        $result2 = $this->dm->find(Foo::class, 'foo_test_document');
        self::assertEquals(\spl_object_hash($result), \spl_object_hash($result2));
    }

    public function testFindShouldLoadProxyWithoutLazyFields(): void
    {
        /** @var FooWithLazyField[] $result */
        $result = $this->dm->getRepository(FooWithLazyField::class)
            ->findBy(['stringField' => 'bazbaz']);

        self::assertCount(1, $result);
        $this->assertDumpEquals(<<<EOF
Tests\Fixtures\Document\FooWithLazyField (proxy) {
  +id: "foo_test_document"
  +stringField: "bazbaz"
}
EOF
        , $result[0]);

        self::assertEquals('lazyBaz', $result[0]->lazyField);
    }

    public function testPersistAndFlush(): void
    {
        $document = new Foo();
        $document->id = 'test_persist_and_flush';
        $document->stringField = 'footest_string';

        $this->dm->persist($document);
        $this->dm->flush();

        $result = $this->dm->find(Foo::class, 'test_persist_and_flush');
        self::assertInstanceOf(Foo::class, $result);
        self::assertEquals(\spl_object_hash($document), \spl_object_hash($result));

        $this->dm->clear();

        $result = $this->dm->find(Foo::class, 'test_persist_and_flush');
        self::assertInstanceOf(Foo::class, $result);
        self::assertEquals('footest_string', $document->stringField);
    }

    public function testMergeAndFlush(): void
    {
        $document = new Foo();
        $document->id = 'test_merge_and_flush';
        $document->stringField = 'footest_string';

        $this->dm->persist($document);
        $this->dm->flush();
        $this->dm->clear();

        $document = new Foo();
        $document->id = 'test_merge_and_flush';
        $document->stringField = 'footest_merge_string';

        /** @var Foo $result */
        $result = $this->dm->merge($document);
        $this->dm->flush();

        self::assertEquals('test_merge_and_flush', $result->id);
        $this->dm->clear();

        $result = $this->dm->find(Foo::class, 'test_merge_and_flush');
        self::assertEquals('test_merge_and_flush', $result->id);
        self::assertEquals('footest_merge_string', $result->stringField);
    }

    public function testGetReferenceShouldReturnAReference(): void
    {
        /** @var Foo $document */
        $document = $this->dm->getReference(Foo::class, 'foo_test_document');
        self::assertInstanceOf(ProxyInterface::class, $document);
        self::assertInstanceOf(Foo::class, $document);

        self::assertEquals('foo_test_document', $document->id);
        // Should load extra fields
        self::assertEquals('bazbaz', $document->stringField);
    }

    public function testGetReferenceShouldReturnThePreviousDocument(): void
    {
        /** @var Foo $document */
        $document = $this->dm->find(Foo::class, 'foo_test_document');
        self::assertNotInstanceOf(ProxyInterface::class, $document);
        self::assertInstanceOf(Foo::class, $document);

        $reference = $this->dm->getReference(Foo::class, 'foo_test_document');
        self::assertNotInstanceOf(ProxyInterface::class, $document);
        self::assertInstanceOf(Foo::class, $document);
        self::assertSame($document, $reference);
    }

    public function testUpdateAndFlush(): void
    {
        $document = $this->dm->find(Foo::class, 'foo_test_document');
        self::assertInstanceOf(Foo::class, $document);

        $document->stringField = 'test_string_field';
        $this->dm->flush();

        $this->dm->clear();

        $result = $this->dm->find(Foo::class, 'foo_test_document');
        self::assertEquals('test_string_field', $result->stringField);
    }

    public function testShouldCreateIndexIfNotAutocreating(): void
    {
        $document = new FooNoAutoCreate();
        $document->id = 'test_persist_and_flush';
        $document->stringField = 'footest_string';
        $document->coordinates = Coordinate::create([42.150, 15.35]);

        $this->dm->persist($document);
        $this->dm->flush();

        $index = new Index($this->dm->getDatabase()->getConnection(), 'foo_index_no_auto_create');
        $mapping = [
            'properties' => [
                'stringField' => ['type' => 'text'],
                'coordinates' => ['type' => 'geo_point'],
            ],
        ];

        if (class_exists(Type::class)) {
            self::assertEquals(['foo_index_no_auto_create' => $mapping], (new Type($index, 'foo_index_no_auto_create'))->getMapping());
        } else {
            self::assertEquals($mapping, $index->getMapping());
        }
    }

    public function testShouldCreateIndexOnMergeIfNotAutocreating(): void
    {
        self::resetFixtures($this->dm);

        $document = new FooNoAutoCreate();
        $document->id = 'test_persist_and_flush';
        $document->stringField = 'footest_string';
        $document->coordinates = Coordinate::create([42.150, 15.35]);

        $this->dm->merge($document);
        $this->dm->flush();

        $index = new Index($this->dm->getDatabase()->getConnection(), 'foo_index_no_auto_create');
        $mapping = [
            'properties' => [
                'stringField' => ['type' => 'text'],
                'coordinates' => ['type' => 'geo_point'],
            ],
        ];

        if (class_exists(Type::class)) {
            self::assertEquals(['foo_index_no_auto_create' => $mapping], (new Type($index, 'foo_index_no_auto_create'))->getMapping());
        } else {
            self::assertEquals($mapping, $index->getMapping());
        }
    }
}
