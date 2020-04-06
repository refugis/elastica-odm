<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Type;

use Refugis\ODM\Elastica\Type\StringType;
use Refugis\ODM\Elastica\Type\TypeInterface;

class StringTypeTest extends AbstractPrimitiveTypeTest
{
    public function getType(): TypeInterface
    {
        return new StringType();
    }

    public function getValue(): string
    {
        return 'string';
    }
}
