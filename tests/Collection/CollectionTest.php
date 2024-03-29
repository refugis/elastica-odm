<?php declare(strict_types=1);

namespace Tests\Collection;

use Elastica\Client;
use Elastica\Index;
use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet;
use Elastica\Scroll as ElasticaScroll;
use Elastica\Search as ElasticaSearch;
use Elastica\SearchableInterface;
use Elastica\Type;
use Elasticsearch\Endpoints;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Refugis\ODM\Elastica\Collection\Collection;
use Refugis\ODM\Elastica\Collection\CollectionInterface;
use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Exception\IndexNotFoundException;
use Refugis\ODM\Elastica\Exception\RuntimeException;
use Tests\Fixtures\Document\Foo;
use Tests\Fixtures\Document\FooNoAutoCreate;
use Tests\Traits\DocumentManagerTestTrait;
use Tests\Traits\FixturesTestTrait;

class CollectionTest extends TestCase
{
    use DocumentManagerTestTrait;
    use FixturesTestTrait;
    use ProphecyTrait;

    /**
     * @var SearchableInterface|ObjectProphecy
     */
    private ObjectProphecy $searchable;

    /**
     * @var Query|ObjectProphecy
     */
    private ObjectProphecy $query;

    /**
     * @var Client|ObjectProphecy
     */
    private ObjectProphecy $client;

    private string $documentClass;
    private CollectionInterface $collection;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->client = $this->prophesize(Client::class);
        if (class_exists(Type::class)) {
            $this->searchable = $this->prophesize(Type::class);
            $this->searchable->getIndex()->willReturn($index = $this->prophesize(Index::class));
            $this->searchable->getName()->willReturn('foo_type');
        } else {
            $this->searchable = $this->prophesize(Index::class);
            $index = $this->searchable;
        }

        $index->getClient()->willReturn($this->client);
        $index->getName()->willReturn('foo_index');

        $this->query = $this->prophesize(Query::class);
        $this->documentClass = \stdClass::class;

        $this->collection = new Collection(
            $this->documentClass,
            $this->searchable->reveal()
        );
    }

    public static function setUpBeforeClass(): void
    {
        $dm = self::createDocumentManager();
        self::resetFixtures($dm);
    }

    public function testScrollShouldSetDefaultSortingIfNotSet(): void
    {
        $search = $this->prophesize(ElasticaSearch::class);
        $this->searchable->createSearch($this->query)->shouldBeCalled()->willReturn($search);

        $this->query->hasParam('sort')->willReturn(false);
        $this->query->setSort(['_doc'])->shouldBeCalled();
        $this->query->setParam('seq_no_primary_term', true)->shouldBeCalled();
        $this->query->setParam('version', true)->shouldBeCalled();

        $expiryTime = '1m';

        $search->scroll($expiryTime)->willReturn($this->prophesize(ElasticaScroll::class));
        $this->collection->scroll($this->query->reveal(), $expiryTime);
    }

    /**
     * @group functional
     */
    public function testScroll(): void
    {
        $dm = self::createDocumentManager();

        $collection = $dm->getCollection(Foo::class);
        $scroll = \iterator_to_array($collection->scroll(new Query()), false);
        $resultSet = $scroll[0];

        self::assertCount(3, $resultSet);
        self::assertArrayHasKey('stringField', $resultSet[0]->getSource());
    }

    public function testSearchShouldExecuteTheQuery(): void
    {
        $this->searchable->search($this->query)
            ->shouldBeCalled()
            ->willReturn($this->prophesize(ResultSet::class))
        ;

        $this->collection->search($this->query->reveal());
    }

    public function testCreateSearchShouldWork(): void
    {
        $documentManager = $this->prophesize(DocumentManagerInterface::class);
        $search = $this->collection->createSearch($documentManager->reveal(), $this->query->reveal());

        self::assertEquals($this->query->reveal(), $search->getQuery());
    }

    public function testCountShouldUseSearchableInterfaceCount(): void
    {
        $this->searchable->count($this->query)->shouldBeCalled()->willReturn(10);
        $this->collection->count($this->query->reveal());
    }

    public function testRefreshShouldCallRefreshEndpoint(): void
    {
        $this->searchable->requestEndpoint(new Endpoints\Indices\Refresh())->shouldBeCalled();
        $this->collection->refresh();
    }

    public function testCreateShouldFireIndexRequest(): void
    {
        $endpoint = new Endpoints\Index();
        $endpoint->setParams(['op_type' => 'create']);
        $endpoint->setID('test_id');
        $endpoint->setBody(['field' => 'value']);
        $endpoint->setIndex('foo_index');
        if (class_exists(Type::class)) {
            $endpoint->setType('foo_type');
        }

        $this->client->requestEndpoint($endpoint)
            ->willReturn(new Response(['_id' => 'test_id'], 200))
            ->shouldBeCalled()
        ;

        $this->collection->create('test_id', ['field' => 'value']);
    }

    public function testCreateShouldSetLastInsertId(): void
    {
        $endpoint = new Endpoints\Index();
        $endpoint->setBody(['field' => 'value']);
        $endpoint->setIndex('foo_index');
        if (class_exists(Type::class)) {
            $endpoint->setType('foo_type');
        }

        $this->client->requestEndpoint($endpoint)
            ->willReturn(new Response(['_id' => 'foo_id'], 200))
            ->shouldBeCalled()
        ;

        $this->collection->create(null, ['field' => 'value']);
        self::assertEquals('foo_id', $this->collection->getLastInsertedId());
    }

    public function testCreateShouldThrowIfResponseIsNotOk(): void
    {
        $this->expectException(RuntimeException::class);

        $endpoint = new Endpoints\Index();
        $endpoint->setBody(['field' => 'value']);
        $endpoint->setIndex('foo_index');
        if (class_exists(Type::class)) {
            $endpoint->setType('foo_type');
        }

        $this->client->requestEndpoint($endpoint)
            ->willReturn(new Response(['_id' => 'foo_id'], 409))
            ->shouldBeCalled()
        ;

        $this->collection->create(null, ['field' => 'value']);
    }

    public function testGetNameShouldReturnTheNameOfTheIndexAndType(): void
    {
        self::assertEquals(class_exists(Type::class) ? 'foo_index/foo_type' : 'foo_index', $this->collection->getName());
    }

    public function testGetNameShouldReturnTheNameOfTheIndexInCaseTypeDoesNotExists(): void
    {
        $index = $this->prophesize(Index::class);
        $index->getName()->willReturn('foo_index');
        $collection = new Collection($this->documentClass, $index->reveal());

        self::assertEquals('foo_index', $collection->getName());
    }

    /**
     * @group functional
     */
    public function testSearchShouldThrowCorrectExceptionOnNonExistentIndex(): void
    {
        $this->expectException(IndexNotFoundException::class);
        $dm = self::createDocumentManager();

        $collection = $dm->getCollection(FooNoAutoCreate::class);
        $collection->search(Query::create(null));
    }

    /**
     * @group functional
     */
    public function testCreate(): void
    {
        $dm = self::createDocumentManager();

        $collection = $dm->getCollection(Foo::class);
        $response = $collection->create('test_index_create', ['stringField' => 'value']);

        self::assertTrue($response->isOk());
        self::assertEquals('test_index_create', $collection->getLastInsertedId());
    }

    /**
     * @group functional
     */
    public function testCreateShouldThrowOnDuplicates(): void
    {
        $this->expectException(RuntimeException::class);

        $dm = self::createDocumentManager();

        $collection = $dm->getCollection(Foo::class);
        $response = $collection->create('test_index_create_duplicate', ['stringField' => 'value']);
        $collection->refresh();

        self::assertTrue($response->isOk());

        $collection->create('test_index_create_duplicate', ['stringField' => 'value']);
    }

    /**
     * @group functional
     */
    public function testCreateWithAutoGeneratedId(): void
    {
        $dm = self::createDocumentManager();

        $collection = $dm->getCollection(Foo::class);
        $response = $collection->create(null, ['stringField' => 'value']);

        self::assertTrue($response->isOk());
        self::assertNotNull($collection->getLastInsertedId());
    }
}
