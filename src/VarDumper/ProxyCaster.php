<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\VarDumper;

use ProxyManager\Proxy\ProxyInterface;
use Symfony\Component\VarDumper\Cloner\Stub;

use function get_class;
use function get_parent_class;
use function strpos;

final class ProxyCaster
{
    public static function castProxy(ProxyInterface $proxy, array $a, Stub $stub, bool $isNested): array
    {
        $stub->class = get_parent_class($proxy) . ' (proxy)';
        $prefix = "\0" . get_class($proxy) . "\0";
        foreach ($a as $key => $value) {
            if (strpos($key, $prefix . 'initializationTracker') !== 0 && strpos($key, $prefix . 'initializer') !== 0) {
                continue;
            }

            unset($a[$key]);
        }

        return $a;
    }
}
