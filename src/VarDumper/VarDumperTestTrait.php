<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\VarDumper;

use ProxyManager\Proxy\ProxyInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait as BaseTrait;

use function getenv;
use function rtrim;

trait VarDumperTestTrait
{
    use BaseTrait;

    /**
     * @param mixed $data
     * @param array-key|null $key
     */
    protected function getDump($data, $key = null, int $filter = 0): ?string
    {
        $flags = getenv('DUMP_LIGHT_ARRAY') ? CliDumper::DUMP_LIGHT_ARRAY : 0;
        $flags |= getenv('DUMP_STRING_LENGTH') ? CliDumper::DUMP_STRING_LENGTH : 0;
        $flags |= getenv('DUMP_COMMA_SEPARATOR') ? CliDumper::DUMP_COMMA_SEPARATOR : 0;

        $cloner = new VarCloner();
        $cloner->addCasters([ProxyInterface::class => ProxyCaster::class . '::castProxy']);
        $cloner->setMaxItems(-1);

        $dumper = new CliDumper(null, null, $flags);
        $dumper->setColors(false);
        $data = $cloner->cloneVar($data, $filter)->withRefHandles(false);
        if ($key !== null && ($data = $data->seek($key)) === null) { // phpcs:ignore SlevomatCodingStandard.ControlStructures.AssignmentInCondition.AssignmentInCondition
            return null;
        }

        return rtrim($dumper->dump($data, true));
    }
}
