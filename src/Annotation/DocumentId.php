<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Attribute;
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
final class DocumentId
{
    /**
     * Id generator strategy.
     *
     * @Enum({"auto", "none"})
     */
    public string $strategy = 'auto';

    public function __construct($strategy = 'auto')
    {
        if (is_string($strategy)) {
            $data = ['strategy' => $strategy];
        } elseif (is_array($strategy)) {
            $data = $strategy;
        } else {
            throw new TypeError(sprintf('Argument #1 passed to %s must be a string. %s passed', __METHOD__, get_debug_type($strategy)));
        }

        $this->strategy = $data['strategy'] ?? 'auto';

        if ($this->strategy !== 'auto' && $this->strategy !== 'none') {
            throw new TypeError(sprintf('Strategy must be one of "auto" or "none", %s given', $this->strategy));
        }
    }
}
