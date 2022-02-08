<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Exception;

use function get_class;
use function method_exists;
use function spl_object_id;

class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
    /**
     * Helper method to show an object as string.
     */
    protected static function objToStr(object $obj): string
    {
        return method_exists($obj, '__toString') ? (string) $obj : get_class($obj) . '@' . spl_object_id($obj);
    }
}
