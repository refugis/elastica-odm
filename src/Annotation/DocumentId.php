<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
final class DocumentId
{
    /**
     * Id generator strategy.
     *
     * @var string
     * @Enum({"auto", "none"})
     */
    public $strategy = 'auto';
}
