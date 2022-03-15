<?php declare(strict_types=1);

namespace Tests;

use Doctrine\Common\Collections\ArrayCollection;
use Elastica\Index;
use Elastica\Type;
use PHPUnit\Framework\TestCase;
use ProxyManager\Proxy\ProxyInterface;
use Refugis\ODM\Elastica\DocumentManager;
use Refugis\ODM\Elastica\Exception\VersionConflictException;
use Refugis\ODM\Elastica\Geotools\Coordinate\Coordinate;
use Tests\Fixtures\Document\Foo;
use Tests\Fixtures\Document\FooEmbeddable;
use Tests\Fixtures\Document\FooNestedEmbeddable;
use Tests\Fixtures\Document\FooNoAutoCreate;
use Tests\Fixtures\Document\FooWithEmbedded;
use Tests\Fixtures\Document\FooWithLazyField;
use Tests\Fixtures\Document\FooWithVersion;
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
        $repository = $this->dm->getRepository(FooWithLazyField::class);
        /** @var FooWithLazyField[] $result */
        $result = $repository->findBy(['stringField' => 'bazbaz']);

        self::assertCount(1, $result);
        $this->assertDumpEquals(<<<EOF
        Tests\Fixtures\Document\FooWithLazyField (proxy) {
          +id: "foo_test_document"
          +stringField: "bazbaz"
        }
        EOF, $result[0]);

        self::assertEquals('lazyBaz', $result[0]->lazyField);
        self::assertEquals(new ArrayCollection(), $result[0]->multiLazyField);

        $result = $repository->findBy(['stringField' => 'barbaz'])[0];
        $this->assertDumpMatchesFormat(<<<EOF
        Tests\Fixtures\Document\FooWithLazyField (proxy) {
          +id: "%a"
          +stringField: "barbaz"
        }
        EOF, $result);

        self::assertEquals('lazyBar', $result->lazyField);
        self::assertEquals(new ArrayCollection(['multiLazy']), $result->multiLazyField);

        $result = $repository->findBy(['stringField' => 'foobar'])[0];
        self::assertNull($result->multiLazyField);

    }

    public function testPersistAndFlush(): void
    {
        $document = new Foo();
        $document->id = 'test_persist_and_flush';
        $document->stringField = 'footest_string';
        $document->multiStringField = new ArrayCollection(['footest_multistring_1', 'footest_multistring_2']);

        $this->dm->persist($document);
        $this->dm->flush();

        $result = $this->dm->find(Foo::class, 'test_persist_and_flush');
        self::assertInstanceOf(Foo::class, $result);
        self::assertEquals(\spl_object_hash($document), \spl_object_hash($result));

        $this->dm->clear();

        $result = $this->dm->find(Foo::class, 'test_persist_and_flush');
        self::assertInstanceOf(Foo::class, $result);
        self::assertEquals('footest_string', $document->stringField);
        self::assertEquals(['footest_multistring_1', 'footest_multistring_2'], $document->multiStringField->toArray());
    }

    public function testMergeAndFlush(): void
    {
        $document = new Foo();
        $document->id = 'test_merge_and_flush';
        $document->stringField = 'footest_string';
        $document->multiStringField = new ArrayCollection(['footest_multistring_1', 'footest_multistring_2']);

        $this->dm->persist($document);
        $this->dm->flush();
        $this->dm->clear();

        $document = new Foo();
        $document->id = 'test_merge_and_flush';
        $document->stringField = 'footest_merge_string';
        $document->multiStringField = new ArrayCollection(['footest_merge_multistring_1']);

        /** @var Foo $result */
        $result = $this->dm->merge($document);
        $this->dm->flush();

        self::assertEquals('test_merge_and_flush', $result->id);
        $this->dm->clear();

        $result = $this->dm->find(Foo::class, 'test_merge_and_flush');
        self::assertInstanceOf(Foo::class, $result);
        self::assertEquals('test_merge_and_flush', $result->id);
        self::assertEquals('footest_merge_string', $result->stringField);
        self::assertEquals(['footest_merge_multistring_1'], $result->multiStringField->toArray());
        self::assertNotNull($result->version);
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

    public function testUpdateOptimisticLockingFails(): void
    {
        $this->expectException(VersionConflictException::class);
        $this->expectExceptionMessage('Version conflict');

        $document = $this->dm->find(Foo::class, 'foo_test_document');
        self::assertInstanceOf(Foo::class, $document);
        $document->seqNo += 50;

        $document->stringField = 'test_string_field';
        $this->dm->flush();
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

    public function testShouldPersistDocumentWithEmbeddedField(): void
    {
        $document = new FooWithEmbedded();
        $document->id = 'test_persist_with_embedded';
        $document->emb = new FooEmbeddable();
        $document->emb->stringField = __METHOD__;
        $document->emb->nestedEmbeddable = new FooNestedEmbeddable();
        $document->emb->nestedEmbeddable->stringFieldRenest = __FUNCTION__;

        $this->dm->persist($document);
        $this->dm->flush();
        $this->dm->clear();

        $result = $this->dm->find(FooWithEmbedded::class, 'test_persist_with_embedded');
        self::assertInstanceOf(FooWithEmbedded::class, $result);
        self::assertEquals('test_persist_with_embedded', $result->id);
        self::assertNotNull($result->emb);
        self::assertEquals(__METHOD__, $result->emb->stringField);

        self::assertInstanceOf(FooNestedEmbeddable::class, $result->emb->nestedEmbeddable);
        self::assertEquals(__FUNCTION__, $result->emb->nestedEmbeddable->stringFieldRenest);

        $result->emb->stringField = __FUNCTION__;
        $result->emb->nestedEmbeddable->stringFieldRenest = __METHOD__;
        $this->dm->flush();
        $this->dm->clear();

        $result = $this->dm->find(FooWithEmbedded::class, 'test_persist_with_embedded');
        self::assertInstanceOf(FooWithEmbedded::class, $result);
        self::assertEquals('test_persist_with_embedded', $result->id);
        self::assertNotNull($result->emb);
        self::assertEquals(__FUNCTION__, $result->emb->stringField);
        self::assertEquals(__METHOD__, $result->emb->nestedEmbeddable->stringFieldRenest);
    }

    public function testShouldPersistWithExternalVersioning(): void
    {
        $document = new FooWithVersion();
        $document->id = 'test_persist_with_version';
        $document->stringField = __METHOD__;
        $document->version = 5;

        $this->dm->persist($document);
        $this->dm->flush();
        $this->dm->clear();

        $result = $this->dm->find(FooWithVersion::class, 'test_persist_with_version');
        self::assertInstanceOf(FooWithVersion::class, $result);
        self::assertEquals('test_persist_with_version', $result->id);
        self::assertEquals(__METHOD__, $result->stringField);
        self::assertEquals(5, $result->version);

        $this->dm->clear();

        $document = new FooWithVersion();
        $document->id = 'test_persist_with_version';
        $document->stringField = 'foobar';
        $document->version = 2;

        $this->dm->persist($document);
        try {
            $this->dm->flush();
            self::fail('Expected exception');
        } catch (VersionConflictException $e) {
            // Do nothing
        }

        $this->dm->clear();

        $result = $this->dm->find(FooWithVersion::class, 'test_persist_with_version');
        self::assertInstanceOf(FooWithVersion::class, $result);
        self::assertEquals('test_persist_with_version', $result->id);
        self::assertEquals(__METHOD__, $result->stringField);
        self::assertEquals(5, $result->version);
    }
}
