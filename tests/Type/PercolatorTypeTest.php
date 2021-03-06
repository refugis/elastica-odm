<?php declare(strict_types=1);

namespace Tests\Type;

use Elastica\Query;
use PHPUnit\Framework\TestCase;
use Refugis\ODM\Elastica\Type\PercolatorType;
use Refugis\ODM\Elastica\Type\TypeInterface;

class PercolatorTypeTest extends TestCase
{
    public function testToPhpWithEmptyValueShouldReturnNull(): void
    {
        $type = $this->getType();
        self::assertNull($type->toPHP(null));
    }

    public function testToPhpShouldWork(): void
    {
        $type = $this->getType();

        $query = Query::create(['query' => ['match' => ['field' => 'value']]]);
        self::assertEquals($query, $type->toPHP(['match' => ['field' => 'value']]));
    }

    public function testToDatabaseWithNullValueShouldReturnNull(): void
    {
        $type = $this->getType();
        self::assertEquals(null, $type->toDatabase(null));
    }

    public function testToDatabaseShouldWork(): void
    {
        $type = $this->getType();
        $value = new Query\MatchPhrase('field', 'value');

        self::assertEquals(['match_phrase' => ['field' => 'value']], $type->toDatabase($value));
        self::assertEquals(['match_phrase' => ['field' => 'value']], $type->toDatabase(Query::create($value)));
    }

    public function getType(): TypeInterface
    {
        return new PercolatorType();
    }
}
