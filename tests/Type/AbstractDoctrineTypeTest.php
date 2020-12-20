<?php declare(strict_types=1);

namespace Tests\Type;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Tests\Fixtures\Type\TestDoctrineType;
use Refugis\ODM\Elastica\Type\TypeInterface;

class AbstractDoctrineTypeTest extends TestCase implements TypeTestInterface
{
    use ProphecyTrait;

    /**
     * @var ManagerRegistry|ObjectProphecy
     */
    private ObjectProphecy $managerRegistry;

    protected function setUp(): void
    {
        $this->managerRegistry = $this->prophesize(ManagerRegistry::class);
    }

    public function getType(): TypeInterface
    {
        return new TestDoctrineType($this->managerRegistry->reveal());
    }

    public function testToPhpValueShouldThrowWhenMandatoryClassNameIsMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $type = $this->getType();
        $value = 'i_am_a_value';

        $type->toPHP($value);
    }

    public function testToPhpWithEmptyValueShouldReturnNull(): void
    {
        $type = $this->getType();
        self::assertNull($type->toPHP(null));
    }

    public function testToPhpValueShouldFindTheDesiredDocument(): void
    {
        $type = $this->getType();

        $value = ['identifier' => 'identifier'];

        $fqcn = 'Fully\\Qualified\\Class\\Name';

        /** @var ObjectManager|ObjectProphecy $manager */
        $manager = $this->prophesize(ObjectManager::class);
        $this->managerRegistry->getManagerForClass($fqcn)->willReturn($manager);

        $manager->find($fqcn, 'identifier')->shouldBeCalled();

        $type->toPHP($value, ['class' => $fqcn]);
    }
}
