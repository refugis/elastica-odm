<?php declare(strict_types=1);

namespace Tests\Type;

use PHPUnit\Framework\TestCase;
use Refugis\ODM\Elastica\Type\RawType;

class RawTypeTest extends TestCase
{
    public function rawData(): array
    {
        return [
            ['this_is_a_raw_string'],
            [123],
            [1.10],
            [-40],
            [[]],
            [new \stdClass()],
            [true],
            [false],
            [null],
        ];
    }

    /**
     * @dataProvider rawData
     *
     * @param mixed $value
     */
    public function testRawTypeToPHPShouldReturnTheSameValue($value): void
    {
        $rawType = new RawType();

        self::assertEquals($value, $rawType->toPHP($value));
    }
}
