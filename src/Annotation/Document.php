<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Annotation;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
final class Document
{
    /**
     * The elastica index/type name.
     *
     * @var string
     */
    public $type;

    /**
     * The repository class.
     *
     * @var string
     */
    public $repositoryClass;
}
