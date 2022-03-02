<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation\Target;
use TypeError;

use function get_debug_type;
use function is_array;
use function is_string;
use function Safe\sprintf;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ParentDocument
{
    /**
     * The field name.
     */
    public ?string $target;

    /**
     * @param string|array<string, mixed> $target
     */
    public function __construct($target)
    {
        if (is_string($target)) {
            $data = ['target' => $target];
        } elseif (is_array($target)) {
            $data = $target;
        } else {
            throw new TypeError(sprintf('Argument #1 passed to %s must be a string. %s passed', __METHOD__, get_debug_type($target)));
        }

        $this->target = $data['target'] ?? null;
    }
}
