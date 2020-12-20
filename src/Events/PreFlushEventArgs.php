<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Events;

use Doctrine\Common\EventArgs;
use Refugis\ODM\Elastica\DocumentManagerInterface;

class PreFlushEventArgs extends EventArgs
{
    private DocumentManagerInterface $dm;

    public function __construct(DocumentManagerInterface $dm)
    {
        $this->dm = $dm;
    }

    public function getDocumentManager(): DocumentManagerInterface
    {
        return $this->dm;
    }
}
