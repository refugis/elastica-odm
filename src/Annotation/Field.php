<?php declare(strict_types=1);

namespace Fazland\ODM\Elastica\Annotation;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
final class Field
{
    /**
     * The field name.
     *
     * @var string
     */
    public $name;

    /**
     * Field type.
     *
     * @var string
     */
    public $type = 'raw';

    /**
     * Field options.
     *
     * @var array
     */
    public $options = [];

    /**
     * Whether to load this field in lazy mode.
     *
     * @var bool
     */
    public $lazy = false;
}
