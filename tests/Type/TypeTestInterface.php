<?php declare(strict_types=1);

namespace Tests\Type;

use Refugis\ODM\Elastica\Type\TypeInterface;

interface TypeTestInterface
{
    public function getType(): TypeInterface;
}
