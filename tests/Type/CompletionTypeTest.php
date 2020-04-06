<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Type;

use PHPUnit\Framework\TestCase;
use Refugis\ODM\Elastica\Completion;
use Refugis\ODM\Elastica\Type\CompletionType;
use Refugis\ODM\Elastica\Type\TypeInterface;

class CompletionTypeTest extends TestCase
{
    public function testToPhpShouldWork(): void
    {
        $type = $this->getType();
        $value = new Completion();
        $value->input = ['The Beatles', 'Beatles'];

        self::assertEquals($value, $type->toPHP([
            'input' => ['The Beatles', 'Beatles'],
        ]));
    }

    public function testToPhpWithEmptyValueShouldReturnNull(): void
    {
        $type = $this->getType();

        self::assertEquals(null, $type->toPHP(null));
    }

    public function testToDatabaseWithNullValueShouldReturnNull(): void
    {
        $type = $this->getType();
        self::assertEquals(null, $type->toDatabase(null));
    }

    public function testToDatabaseShouldWork(): void
    {
        $type = $this->getType();
        $value = new Completion();
        $value->input = ['The Beatles', 'Beatles'];
        self::assertEquals([
            'input' => ['The Beatles', 'Beatles'],
        ], $type->toDatabase($value));
    }

    public function getType(): TypeInterface
    {
        return new CompletionType();
    }
}
