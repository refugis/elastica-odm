<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Type;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Refugis\ODM\Elastica\Exception\NoSuchTypeException;
use Refugis\ODM\Elastica\Type\TypeInterface;
use Refugis\ODM\Elastica\Type\TypeManager;

class TypeManagerTest extends TestCase
{
    use ProphecyTrait;

    public function testGetTypeShouldThrowOnUnknownType(): void
    {
        $this->expectException(NoSuchTypeException::class);

        $typeManager = new TypeManager();
        $typeManager->getType('unknown_type');
    }

    public function testGetTypeShouldWork(): void
    {
        /** @var TypeInterface|ObjectProphecy $type */
        $type = $this->prophesize(TypeInterface::class);
        $type->getName()->willReturn('type_name');

        $typeManager = new TypeManager();
        $typeManager->addType($type->reveal());

        self::assertEquals($typeManager->getType('type_name'), $type->reveal());
    }
}
