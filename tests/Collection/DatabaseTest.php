<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Collection;

use Elastica\Client;
use Elastica\Index;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Refugis\ODM\Elastica\Collection\Database;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

class DatabaseTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var Client|ObjectProphecy
     */
    private ObjectProphecy $client;
    private Database $database;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->client = $this->prophesize(Client::class);
        $this->database = new Database($this->client->reveal());
    }

    public function testGetCollectionCalledMoreThanOnceShouldRetrieveTheSameCollectionInstance(): void
    {
        $class = new DocumentMetadata(new \ReflectionClass(\stdClass::class));
        $class->name = 'document_name';
        $class->collectionName = 'type_name';

        $this->client->getIndex($class->collectionName)
            ->shouldBeCalledTimes(1)
            ->willReturn($index = $this->prophesize(Index::class))
        ;

        $index->getName()->willReturn('type_name');

        $collection = $this->database->getCollection($class);
        $collection2 = $this->database->getCollection($class);

        self::assertEquals($collection, $collection2);
    }
}
