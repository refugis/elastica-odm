<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Type;

use Refugis\ODM\Elastica\Type\BooleanType;
use Refugis\ODM\Elastica\Type\TypeInterface;

class BooleanTypeTest extends AbstractPrimitiveTypeTest
{
    public function getType(): TypeInterface
    {
        return new BooleanType();
    }

    public function getValue(): bool
    {
        return true;
    }
}
