<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Type;

use PHPUnit\Framework\TestCase;
use Refugis\ODM\Elastica\Geotools\Coordinate\Coordinate;
use Refugis\ODM\Elastica\Type\GeoPointType;
use Refugis\ODM\Elastica\Type\TypeInterface;

class GeoPointTypeTest extends TestCase
{
    use EmptyValuesTrait;

    public function testToPhpShouldWork(): void
    {
        $type = $this->getType();
        $value = new Coordinate([45, 27]);

        self::assertEquals($value, $type->toPHP([27, 45]));
    }

    public function testToDatabaseWithNullValueShouldReturnNull(): void
    {
        $type = $this->getType();
        self::assertEquals(null, $type->toDatabase(null));
    }

    public function testToDatabaseShouldWork(): void
    {
        $type = $this->getType();
        $value = new Coordinate([45, 27]);
        self::assertEquals(['lat' => 45.0, 'lon' => 27.0], $type->toDatabase($value));
    }

    public function getType(): TypeInterface
    {
        return new GeoPointType();
    }
}
