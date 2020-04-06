<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Collection;

use Elastica\Client;
use Elastica\Index;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Refugis\ODM\Elastica\Collection\Database;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

class DatabaseTest extends TestCase
{
    /**
     * @var Client|ObjectProphecy
     */
    private $client;

    /**
     * @var Database
     */
    private $database;

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
            ->willReturn($this->prophesize(Index::class))
        ;

        $collection = $this->database->getCollection($class);
        $collection2 = $this->database->getCollection($class);

        self::assertEquals($collection, $collection2);
    }
}
