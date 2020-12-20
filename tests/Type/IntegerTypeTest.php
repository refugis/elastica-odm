<?php declare(strict_types=1);

namespace Tests\Type;

use Refugis\ODM\Elastica\Type\IntegerType;
use Refugis\ODM\Elastica\Type\TypeInterface;

class IntegerTypeTest extends AbstractPrimitiveTypeTest
{
    public function getType(): TypeInterface
    {
        return new IntegerType();
    }

    public function getValue(): int
    {
        return 123;
    }
}
