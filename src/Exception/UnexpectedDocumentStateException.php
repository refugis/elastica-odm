<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Exception;

use Refugis\ODM\Elastica\UnitOfWork;
use Throwable;

use function Safe\sprintf;

class UnexpectedDocumentStateException extends InvalidArgumentException
{
    public function __construct(int $state, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('Unexpected document state "%s"', self::getStateAsString($state)), 0, $previous);
    }

    /**
     * Gets the state string.
     */
    private static function getStateAsString(int $state): string
    {
        switch ($state) {
            case UnitOfWork::STATE_MANAGED:
                return 'MANAGED';

            case UnitOfWork::STATE_NEW:
                return 'NEW';

            case UnitOfWork::STATE_DETACHED:
                return 'DETACHED';

            case UnitOfWork::STATE_REMOVED:
                return 'REMOVED';

            default:
                return sprintf('UNKNOWN (%u)', $state);
        }
    }
}
