<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Doctrine\Common\Annotations\Annotation\Target;

use const Attribute;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
#[Attribute]
final class TypeName
{
}
