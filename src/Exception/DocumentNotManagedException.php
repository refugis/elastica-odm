<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Exception;

use Throwable;

class DocumentNotManagedException extends InvalidArgumentException
{
    public function __construct(object $document, Throwable $previous = null)
    {
        parent::__construct(\sprintf('Document %s is not managed. A document is managed if it\'s fetched from the database or registered as new through DocumentManager#persist', self::objToStr($document)), 0, $previous);
    }
}
