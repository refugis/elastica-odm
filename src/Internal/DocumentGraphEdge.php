<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Internal;

/**
 * Represents a document dependency graph edge.
 *
 * @internal
 */
final class DocumentGraphEdge
{
    /** @var DocumentGraphNode */
    private $source;

    /** @var DocumentGraphNode */
    private $destination;

    public function __construct(DocumentGraphNode $source, DocumentGraphNode $destination)
    {
        $this->source = $source;
        $this->destination = $destination;
    }

    public function getSource(): DocumentGraphNode
    {
        return $this->source;
    }

    public function getDestination(): DocumentGraphNode
    {
        return $this->destination;
    }
}
