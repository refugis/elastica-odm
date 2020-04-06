<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Type;

use Refugis\ODM\Elastica\Type\FloatType;
use Refugis\ODM\Elastica\Type\TypeInterface;

class FloatTypeTest extends AbstractPrimitiveTypeTest
{
    public function getType(): TypeInterface
    {
        return new FloatType();
    }

    public function getValue(): float
    {
        return 456.1;
    }
}
