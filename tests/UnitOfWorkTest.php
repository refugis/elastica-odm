<?php declare(strict_types=1);

namespace Tests;

use Doctrine\Common\EventManager;
use Elastica\Client;
use Elastica\Response;
use Elastica\Transport\AbstractTransport;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Refugis\ODM\Elastica\Collection\Database;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Persister\DocumentPersister;
use Tests\Fixtures\Document\CMS\CmsPhoneNumber;
use Tests\Fixtures\Document\Forum\ForumUser;
use Tests\Fixtures\Document\GeoNames\City;
use Tests\Fixtures\Document\GeoNames\Country;
use Tests\Mocks\DocumentManagerMock;
use Tests\Mocks\DocumentPersisterMock;
use Refugis\ODM\Elastica\UnitOfWork;

class UnitOfWorkTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var AbstractTransport|ObjectProphecy
     */
    private ObjectProphecy $transport;

    /**
     * @var EventManager|ObjectProphecy
     */
    private ObjectProphecy $eventManager;

    private DocumentManagerMock $dm;
    private UnitOfWork $unitOfWork;

    protected function setUp(): void
    {
        $this->transport = $this->prophesize(AbstractTransport::class);
        $this->transport->setConnection(Argument::any())->willReturn($this->transport);
        $this->eventManager = $this->prophesize(EventManager::class);

        $database = new Database(new Client([
            'transport' => $this->transport->reveal(),
        ]));

        $this->dm = DocumentManagerMock::create($database, null, $this->eventManager->reveal());

        $this->unitOfWork = new UnitOfWork($this->dm);
        $this->dm->setUnitOfWork($this->unitOfWork);
    }

    public function testRegisterRemovedOnNewEntityIsIgnored(): void
    {
        $user = new ForumUser();
        $user->username = 'romanb';

        self::assertFalse($this->unitOfWork->isScheduledForDelete($user));
        $this->unitOfWork->remove($user);
        self::assertFalse($this->unitOfWork->isScheduledForDelete($user));
    }

    public function testSavingSingleDocumentWithIdentityFieldForcesInsert(): void
    {
        $this->transport->exec(Argument::cetera())->willReturn($this->prophesize(Response::class));
        $this->transport->toArray()->will(function () {
            return [];
        });

        $persister = new DocumentPersisterMock($this->dm, $this->dm->getClassMetadata(ForumUser::class));
        $persister->setMockGeneratorType(DocumentMetadata::GENERATOR_TYPE_AUTO);

        self::setDocumentPersister($this->unitOfWork, $persister, ForumUser::class);

        $user = new ForumUser();
        $user->username = 'romanb';
        $this->unitOfWork->persist($user);

        self::assertCount(0, $persister->getInserts());
        self::assertCount(0, $persister->getUpdates());
        self::assertCount(0, $persister->getDeletes());

        self::assertFalse($this->unitOfWork->isInIdentityMap($user));
        self::assertTrue($this->unitOfWork->isScheduledForInsert($user));

        $this->unitOfWork->commit();

        self::assertCount(1, $persister->getInserts());
        self::assertCount(0, $persister->getUpdates());
        self::assertCount(0, $persister->getDeletes());

        self::assertNotEmpty($user->id);
    }

    public function testGetEntityStateWithAssignedIdentity(): void
    {
        $persister = new DocumentPersisterMock($this->dm, $this->dm->getClassMetadata(CmsPhoneNumber::class));
        self::setDocumentPersister($this->unitOfWork, $persister, CmsPhoneNumber::class);

        $ph = new CmsPhoneNumber();
        $ph->phonenumber = '12345';

        self::assertEquals(UnitOfWork::STATE_NEW, $this->unitOfWork->getDocumentState($ph));
        self::assertTrue($persister->isExistsCalled());

        $persister->reset();

        $this->unitOfWork->registerManaged($ph, []);
        self::assertEquals(UnitOfWork::STATE_MANAGED, $this->unitOfWork->getDocumentState($ph));
        self::assertFalse($persister->isExistsCalled());

        $ph2 = new CmsPhoneNumber();
        $ph2->phonenumber = '12345';

        self::assertEquals(UnitOfWork::STATE_DETACHED, $this->unitOfWork->getDocumentState($ph2));
        self::assertFalse($persister->isExistsCalled());
    }

    public function testRemovedAndRePersistedDocumentsAreInTheIdentityMapAndAreNotGarbageCollected(): void
    {
        $document = new ForumUser();
        $document->id = '123';

        $this->unitOfWork->registerManaged($document, []);
        self::assertTrue($this->unitOfWork->isInIdentityMap($document));

        $this->unitOfWork->remove($document);
        self::assertFalse($this->unitOfWork->isInIdentityMap($document));

        $this->unitOfWork->persist($document);
        self::assertTrue($this->unitOfWork->isInIdentityMap($document));
    }

    public function testPersistedDocumentAndClearManager(): void
    {
        $document1 = new City('123', 'London');
        $document2 = new Country('456', 'United Kingdom');

        $this->unitOfWork->persist($document1);
        self::assertTrue($this->unitOfWork->isInIdentityMap($document1));

        $this->unitOfWork->persist($document2);
        self::assertTrue($this->unitOfWork->isInIdentityMap($document2));

        $this->unitOfWork->clear(Country::class);
        self::assertTrue($this->unitOfWork->isInIdentityMap($document1));
        self::assertFalse($this->unitOfWork->isInIdentityMap($document2));
        self::assertTrue($this->unitOfWork->isScheduledForInsert($document1));
        self::assertFalse($this->unitOfWork->isScheduledForInsert($document2));
    }

    private static function setDocumentPersister(UnitOfWork $uow, DocumentPersister $documentPersister, string $class): void
    {
        (function (DocumentPersister $persister, string $class): void {
            $this->documentPersisters[$class] = $persister;
        })->bindTo($uow, UnitOfWork::class)($documentPersister, $class);
    }
}
