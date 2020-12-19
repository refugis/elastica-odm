<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Util;

use Doctrine\Persistence\Proxy;
use ProxyManager\Proxy\ProxyInterface;

final class ClassUtil
{
    private function __construct()
    {
        // Cannot be instantiated.
    }

    /**
     * Gets the object "real" class.
     */
    public static function getClass(object $object): string
    {
        $class = \get_class($object);
        if ($object instanceof ProxyInterface || $object instanceof Proxy || false !== \strpos($class, '\\__PM__\\')) {
            $class = \get_parent_class($object);
        }

        return $class;
    }
}
