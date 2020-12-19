<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Id;

use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Exception\InvalidIdentifierException;

interface GeneratorInterface
{
    /**
     * Generates a document identifier.
     *
     * @return mixed
     *
     * @throws InvalidIdentifierException if id cannot be generated or invalid
     */
    public function generate(DocumentManagerInterface $dm, object $document);

    /**
     * Whether this generator must be called after the insert operation.
     */
    public function isPostInsertGenerator(): bool;
}
