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
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Document
{
    /**
     * The elastica index/type name.
     */
    public ?string $collection = null;

    /**
     * The repository class.
     */
    public ?string $repositoryClass = null;

    /**
     * Whether the document is readonly.
     */
    public ?bool $readOnly = null;

    /**
     * The label of join type for this document class.
     */
    public ?string $joinType = null;

    /**
     * The name of join field type for this document class. Defaults to "joinField".
     */
    public ?string $joinFieldName = null;

    public function __construct(
        $collection = null,
        ?string $repositoryClass = null,
        ?string $joinType = null,
        ?string $joinFieldName = null
    ) {
        if ($collection === null || is_string($collection)) {
            $data = ['collection' => $collection];
        } elseif (is_array($collection)) {
            if (isset($data['type']) && ! isset($data['collection'])) {
                trigger_error('Setting "type" on Document annotation is deprecated, use "collection" instead', E_USER_DEPRECATED);
                $data['collection'] = $data['type'];
                unset($data['type']);
            }

            $data = $collection;
        } else {
            throw new TypeError(sprintf('Argument #1 passed to %s must be a string. %s passed', __METHOD__, get_debug_type($type)));
        }

        $this->collection = $data['collection'] ?? null;
        $this->repositoryClass = $data['repositoryClass'] ?? $repositoryClass;
        $this->joinType = $data['joinType'] ?? $joinType;
        $this->joinFieldName = $data['joinFieldName'] ?? $joinFieldName;
    }
}
