<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Events;

use Doctrine\Common\EventManager;
use Refugis\ODM\Elastica\Events;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\UnitOfWork;

class LifecycleEventManager
{
    private UnitOfWork $uow;
    private EventManager $evm;

    public function __construct(UnitOfWork $uow, EventManager $evm)
    {
        $this->uow = $uow;
        $this->evm = $evm;
    }

    public function postPersist(DocumentMetadata $class, $object)
    {
        // @todo
    }

    public function prePersist(DocumentMetadata $class, $object)
    {
        // @todo
    }

    public function preRemove(DocumentMetadata $class, $object)
    {
        // @todo
    }

    public function postRemove(DocumentMetadata $class, $object)
    {
        // @todo
    }

    public function preUpdate(DocumentMetadata $class, $document)
    {
        // @todo Check lifecycle callbacks

        if (! $this->evm->hasListeners(Events::preUpdate)) {
            return;
        }

        $this->evm->dispatchEvent(Events::preUpdate, new PreUpdateEventArgs($document, $this->uow->getObjectManager(), $this->uow->getDocumentChangeSet($document)));
        $this->uow->recomputeSingleDocumentChangeset($document);
    }

    public function postUpdate(DocumentMetadata $class, $document)
    {
        // @todo
    }
}
