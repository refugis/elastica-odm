<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Exception;

use Exception;

use function get_class;
use function gettype;
use function implode;
use function is_array;
use function is_object;

class ConversionFailedException extends Exception implements ExceptionInterface
{
    /**
     * @param mixed $value
     * @param mixed $expected
     */
    public function __construct($value, $expected)
    {
        $expected = is_array($expected) ? $expected : [$expected];
        $given = is_object($value) ? get_class($value) : gettype($value);

        parent::__construct('Conversion failed. Expected ' . implode(' or ', $expected) . ', but ' . $given . ' was given');
    }
}
