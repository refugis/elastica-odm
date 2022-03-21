<?php declare(strict_types=1);

namespace Tests;

use Doctrine\Common\Collections\ArrayCollection;
use Elastica\Index;
use Elastica\Query;
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
use Tests\Fixtures\Document\JoinField\FooChild;
use Tests\Fixtures\Document\JoinField\FooGrandParent;
use Tests\Fixtures\Document\JoinField\FooParent;
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

    public function testShouldPersistJoinRelationships(): void
    {
        self::resetFixtures($this->dm);

        $grandParent = new FooGrandParent();
        $grandParent->id = 'grand_parent_1';

        $parent = new FooParent();
        $parent->id = 'parent_1';
        $parent->fooGrandParent = $grandParent;

        $parent2 = new FooParent();
        $parent2->id = 'parent_2';

        $child1 = new FooChild();
        $child1->id = 'child_1';
        $child1->fooParent = $parent;

        $child2 = new FooChild();
        $child2->id = 'child_2';
        $child2->fooParent = $parent2;

        $child3 = new FooChild();
        $child3->id = 'child_3';

        $this->dm->persist($grandParent);
        $this->dm->persist($parent);
        $this->dm->persist($parent2);
        $this->dm->persist($child1);
        $this->dm->persist($child2);
        $this->dm->persist($child3);

        $this->dm->flush();

        $parents = $this->dm
            ->getRepository(FooParent::class)
            ->findAll();

        self::assertCount(2, $parents);

        $children = $this->dm
            ->getRepository(FooChild::class)
            ->findAll();

        self::assertCount(3, $children);

        $this->dm->clear();

        $child1 = $this->dm->find(FooChild::class, 'child_1');
        self::assertEquals('child_1', $child1->id);
    }

    public function testParentCouldBeChanged(): void
    {
        self::resetFixtures($this->dm);

        $grandParent1 = new FooGrandParent();
        $grandParent1->id = 'grand_parent_changeable_1';

        $grandParent2 = new FooGrandParent();
        $grandParent2->id = 'grand_parent_changeable_2';

        $parent = new FooParent();
        $parent->id = 'parent_1';
        $parent->fooGrandParent = $grandParent1;

        $this->dm->persist($grandParent1);
        $this->dm->persist($grandParent2);
        $this->dm->persist($parent);

        $this->dm->flush();
        $this->dm->clear();

        $parent = $this->dm->find(FooParent::class, 'parent_1');

        self::assertNotNull($parent);
        self::assertInstanceOf(FooParent::class, $parent);
        self::assertEquals('grand_parent_changeable_1', $parent->fooGrandParent->id);

        $parent->fooGrandParent = $grandParent2;
        $this->dm->flush();
        $this->dm->clear();

        $idQuery = new Query\Ids(['parent_1']);
        $q = new Query($idQuery);
        $parents = $this->dm->getRepository(FooParent::class)
            ->createSearch()
            ->setQuery($q)
            ->execute();

        self::assertCount(1, $parents);
        self::assertInstanceOf(FooParent::class, $parents[0]);
        self::assertEquals('grand_parent_changeable_2', $parents[0]->fooGrandParent->id);
    }

    public function testShouldFireMultipleBulkRequestsIfThereAreTooManyOperations(): void
    {
        self::resetFixtures($this->dm);

        $this->dm->getCollection(Foo::class)
            ->deleteByQuery(new Query\MatchAll());

        for ($i = 0; $i < 1024 * 103; ++$i) {
            $document = new Foo();
            $document->id = 'big_foo_' . $i;
            $document->stringField = 'string';

            $this->dm->persist($document);
        }

        $this->dm->flush();
        $this->dm->clear();

        $search = $this->dm->getRepository(Foo::class)->createSearch();
        self::assertEquals(103 * 1024, $search->count());
    }
}
