<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Type;

use Refugis\ODM\Elastica\Type\IpType;
use Refugis\ODM\Elastica\Type\TypeInterface;

class IpTypeTest extends AbstractPrimitiveTypeTest
{
    public function getType(): TypeInterface
    {
        return new IpType();
    }

    public function getValue(): string
    {
        return '192.168.0.1';
    }
}
