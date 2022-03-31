<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Util;

use Doctrine\Persistence\Proxy;
use ProxyManager\Proxy\ProxyInterface;

use function get_class;
use function get_parent_class;
use function strpos;

final class ClassUtil
{
    private function __construct()
    {
        // Cannot be instantiated.
    }

    /**
     * Gets the object "real" class.
     *
     * @param T $object
     *
     * @phpstan-return class-string<T>
     *
     * @template T of object
     */
    public static function getClass(object $object): string
    {
        $class = get_class($object);
        if ($object instanceof ProxyInterface || $object instanceof Proxy || strpos($class, '\\__PM__\\') !== false) {
            $class = get_parent_class($object);
        }

        /* @phpstan-ignore-next-line */
        return $class;
    }
}
