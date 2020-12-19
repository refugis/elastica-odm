<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Doctrine\Common\Annotations\Annotation\Target;
use function Safe\sprintf;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
#[Attribute]
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

    public function __construct($collection = null, ?string $repositoryClass = null)
    {
        if ($collection === null || is_string($collection)) {
            $data = [ 'collection' => $collection ];
        } elseif (is_array($collection)) {
            if (isset($data['type']) && ! isset($data['collection'])) {
                trigger_error('Setting "type" on Document annotation is deprecated, use "collection" instead', E_USER_DEPRECATED);
                $data['collection'] = $data['type'];
                unset($data['type']);
            }

            $data = $collection;
        } else {
            throw new \TypeError(sprintf('Argument #1 passed to %s must be a string. %s passed', __METHOD__, get_debug_type($type)));
        }

        $this->collection = $data['collection'] ?? null;
        $this->repositoryClass = $data['repositoryClass'] ?? $repositoryClass;
    }
}
