<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Exception;

use Throwable;

use function sprintf;

class ReadOnlyRequiresManagedDocumentException extends InvalidArgumentException
{
    public function __construct(object $document, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('Only managed entities can be marked or checked as read only. But %s is not', self::objToStr($document)), 0, $previous);
    }
}
