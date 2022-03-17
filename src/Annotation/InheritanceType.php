<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;
use TypeError;

use function get_debug_type;
use function is_array;
use function is_string;
use function Safe\sprintf;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class InheritanceType
{
    public const SINGLE_INDEX = 'single-index';
    public const PARENT_CHILD = 'parent-child';
    public const INDEX_PER_CLASS = 'index-per-class';

    /**
     * The inheritance type for the current class hierarchy.
     *
     * @Enum({InheritanceType::SINGLE_INDEX, InheritanceType::PARENT_CHILD, InheritanceType::COLLECTION_PER_CLASS})
     * @Required()
     */
    public string $type;

    public function __construct($type)
    {
        if (is_string($type)) {
            $data = ['type' => $type];
        } elseif (is_array($type)) {
            $data = $type;
        } else {
            throw new TypeError(sprintf('Argument #1 passed to %s must be a string. %s passed', __METHOD__, get_debug_type($type)));
        }

        $this->type = $data['type'] ?? $data['value'];
    }
}
