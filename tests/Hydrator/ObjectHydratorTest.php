<?php declare(strict_types=1);

namespace Tests\Hydrator;

use Doctrine\Common\EventManager;
use Elastica\Document;
use Elastica\Exception\InvalidException;
use Elastica\Query;
use Elastica\Response;
use Elastica\Result;
use Elastica\ResultSet;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use ProxyManager\Factory\LazyLoadingGhostFactory;
use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Exception\RuntimeException;
use Refugis\ODM\Elastica\Hydrator\ObjectHydrator;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;
use Tests\Fixtures\Hydrator\TestDocument;
use Refugis\ODM\Elastica\Type\StringType;
use Refugis\ODM\Elastica\Type\TypeManager;
use Refugis\ODM\Elastica\UnitOfWork;

class ObjectHydratorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var EventManager|ObjectProphecy
     */
    private ObjectProphecy $eventManager;
    private TypeManager $typeManager;

    /**
     * @var DocumentManagerInterface|ObjectProphecy
     */
    private ObjectProphecy $documentManager;
    private UnitOfWork $uow;
    private ObjectHydrator $hydrator;

    protected function setUp(): void
    {
        $stringType = new StringType();

        $this->eventManager = $this->prophesize(EventManager::class);
        $this->typeManager = new TypeManager();
        $this->typeManager->addType($stringType);

        $this->documentManager = $this->prophesize(DocumentManagerInterface::class);
        $this->documentManager->getEventManager()->willReturn($this->eventManager);
        $this->documentManager->getTypeManager()->willReturn($this->typeManager);

        $this->uow = new UnitOfWork($this->documentManager->reveal());
        $this->documentManager->getUnitOfWork()->willReturn($this->uow);

        $this->hydrator = new ObjectHydrator($this->documentManager->reveal());
    }

    public function testHydrateOneShouldWork(): void
    {
        $class = $this->getTestDocumentMetadata();
        $this->documentManager->getClassMetadata(TestDocument::class)->willReturn($class);

        $documentId = '12345';
        $expectedDocumentValues = [
            'id' => $documentId,
            'field1' => 'field1',
            'field2' => 'field2',
        ];
        $document = $this->prophesize(Document::class);
        $document->getId()->willReturn($documentId);
        $document->getData()->willReturn($expectedDocumentValues);

        $result = $this->hydrator->hydrateOne($document->reveal(), TestDocument::class);
        $this->assertTestDocumentEquals($expectedDocumentValues, $result);
    }

    public function testHydrateAllShouldThrowIfResponseIsNotOk(): void
    {
        $resultSet = $this->prophesize(ResultSet::class);
        $resultSet->getResponse()->willReturn($response = $this->prophesize(Response::class));
        $resultSet->count()->willReturn(0);

        $response->isOk()->willReturn(false);
        $response->getStatus()->willReturn(400);
        $response->getErrorMessage()->willReturn('Test error');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response not OK [400 Bad Request]: Test error');
        $this->hydrator->hydrateAll($resultSet->reveal(), TestDocument::class);
    }

    public function testHydrateAllShouldReturnEmptyArrayOnEmptyResultSet(): void
    {
        $resultSet = $this->prophesize(ResultSet::class);
        $resultSet->getResponse()->willReturn($response = $this->prophesize(Response::class));
        $resultSet->count()->willReturn(0);

        $response->isOk()->willReturn(true);

        self::assertEmpty($this->hydrator->hydrateAll($resultSet->reveal(), TestDocument::class));
    }

    public function testHydrateAllShouldWork(): void
    {
        $query = $this->prophesize(Query::class);
        $query->getParam('_source')->willThrow(InvalidException::class);

        $result1 = $this->prophesize(Result::class);
        $result2 = $this->prophesize(Result::class);

        $results = [$result1->reveal(), $result2->reveal()];
        $response = $this->prophesize(Response::class);
        $response->isOk()->willReturn(true);
        $resultSet = new ResultSet($response->reveal(), $query->reveal(), $results);

        $document1Id = '12345';
        $expectedDocument1Values = [
            'id' => $document1Id,
            'field1' => 'document1.field1',
            'field2' => 'document1.field2',
        ];
        $document1 = $this->prophesize(Document::class);
        $document1->getId()->willReturn($document1Id);
        $document1->getData()->willReturn($expectedDocument1Values);

        $document2Id = '67890';
        $expectedDocument2Values = [
            'id' => $document2Id,
            'field1' => 'document2.field1',
            'field2' => 'document2.field2',
        ];
        $document2 = $this->prophesize(Document::class);
        $document2->getId()->willReturn($document2Id);
        $document2->getData()->willReturn($expectedDocument2Values);

        $result1->getDocument()->willReturn($document1);
        $result2->getDocument()->willReturn($document2);

        $class = $this->getTestDocumentMetadata();

        $this->documentManager->getClassMetadata(TestDocument::class)->willReturn($class);

        /** @var TestDocument[] $documents */
        $documents = $this->hydrator->hydrateAll($resultSet, TestDocument::class);

        self::assertCount(2, $documents);
        $this->assertTestDocumentEquals($expectedDocument1Values, $documents[0]);
        $this->assertTestDocumentEquals($expectedDocument2Values, $documents[1]);
    }

    public function testHydrateAllWithLazyDocumentShouldWork(): void
    {
        $query = $this->prophesize(Query::class);
        $query->getParam('_source')->willReturn(['id', 'field2']);

        $result = $this->prophesize(Result::class);
        $response = $this->prophesize(Response::class);
        $response->isOk()->willReturn(true);
        $resultSet = new ResultSet($response->reveal(), $query->reveal(), [$result->reveal()]);

        $documentId = '12345';
        $document = $this->prophesize(Document::class);
        $document->getId()->willReturn($documentId);
        $document->getData()->willReturn([
            'id' => $documentId,
            'field2' => 'field2',
        ]);

        $result->getDocument()->willReturn($document);

        $class = $this->getTestLazyDocumentMetadata();
        $this->documentManager->getClassMetadata(TestDocument::class)->willReturn($class);
        $this->documentManager->getProxyFactory()->willReturn(new LazyLoadingGhostFactory());

        /** @var TestDocument[] $documents */
        $documents = $this->hydrator->hydrateAll($resultSet, TestDocument::class);

        self::assertEquals('12345', $documents[0]->getId());
        self::assertEquals('field2', $documents[0]->getField2());

        $this->documentManager->refresh(Argument::any())->shouldNotHaveBeenCalled();
        $this->documentManager->refresh($documents[0])
            ->shouldBeCalledOnce()
            ->will(function () use ($documents) {
                (function () {
                    $this->field1 = 'test_field1';
                })->bindTo($documents[0], TestDocument::class)();
            });

        self::assertEquals('test_field1', $documents[0]->getField1());
    }

    public function getTestDocumentMetadata(): DocumentMetadata
    {
        $class = new DocumentMetadata(new \ReflectionClass(TestDocument::class));
        $id = new FieldMetadata($class, 'id');
        $id->identifier = true;
        $id->type = 'string';
        $id->fieldName = 'id';
        $class->identifier = $id;

        $field1 = new FieldMetadata($class, 'field1');
        $field1->type = 'string';
        $field1->fieldName = 'field1';

        $field2 = new FieldMetadata($class, 'field2');
        $field2->type = 'string';
        $field2->fieldName = 'field2';

        $class->addAttributeMetadata($id);
        $class->addAttributeMetadata($field1);
        $class->addAttributeMetadata($field2);

        return $class;
    }

    public function getTestLazyDocumentMetadata(): DocumentMetadata
    {
        $class = new DocumentMetadata(new \ReflectionClass(TestDocument::class));
        $id = new FieldMetadata($class, 'id');
        $id->identifier = true;
        $id->type = 'string';
        $id->fieldName = 'id';
        $class->identifier = $id;

        $field1 = new FieldMetadata($class, 'field1');
        $field1->type = 'string';
        $field1->fieldName = 'field1';
        $field1->lazy = true;

        $field2 = new FieldMetadata($class, 'field2');
        $field2->type = 'string';
        $field2->fieldName = 'field2';

        $class->addAttributeMetadata($id);
        $class->addAttributeMetadata($field1);
        $class->addAttributeMetadata($field2);

        return $class;
    }

    private function assertTestDocumentEquals(array $expectedValues, TestDocument $document): void
    {
        self::assertEquals($expectedValues['id'], $document->getId());
        self::assertEquals($expectedValues['field1'], $document->getField1());
        self::assertEquals($expectedValues['field2'], $document->getField2());
    }
}
