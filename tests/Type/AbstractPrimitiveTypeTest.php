<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Type;

use Refugis\ODM\Elastica\Type\TypeInterface;
use PHPUnit\Framework\TestCase;

abstract class AbstractPrimitiveTypeTest extends TestCase
{
    abstract public function getType(): TypeInterface;

    abstract public function getValue();

    public function testToPhpWithNullValueShouldReturnNull(): void
    {
        $type = $this->getType();

        self::assertEquals(null, $type->toPHP(null));
    }

    public function testToPhpShouldWork(): void
    {
        $type = $this->getType();

        $value = $this->getValue();
        self::assertEquals($value, $type->toPHP($value));
    }

    public function testToDatabaseWithNullValueShouldReturnNull(): void
    {
        $type = $this->getType();

        self::assertEquals(null, $type->toDatabase(null));
    }

    public function testToDatabaseShouldWork(): void
    {
        $type = $this->getType();

        $value = $this->getValue();
        self::assertEquals($value, $type->toDatabase($value));
    }
}
