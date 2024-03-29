<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Internal;

use Countable;
use Generator;
use IteratorAggregate;

use function count;

/**
 * Represents a document dependency graph node.
 * Count will return the number of nodes which depend on this (in edges).
 * Traverse will iterate through the out edges.
 *
 * @internal
 */
final class DocumentGraphNode implements Countable, IteratorAggregate
{
    private string $className;

    /** @var DocumentGraphEdge[] */
    private array $inEdges;

    /** @var DocumentGraphEdge[] */
    private array $outEdges;

    public function __construct(string $className)
    {
        $this->className = $className;

        $this->inEdges = [];
        $this->outEdges = [];
    }

    /**
     * Adds an in edge to this node.
     */
    public function addInEdge(DocumentGraphEdge $edge): void
    {
        $this->inEdges[$edge->getSource()->className] = $edge;
    }

    /**
     * Adds an out edge from this node.
     */
    public function addOutEdge(DocumentGraphEdge $edge): void
    {
        $this->outEdges[$edge->getDestination()->className] = $edge;
    }

    /**
     * Gets the document class name.
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * {@inheritDoc}
     *
     * Traverse the graph through the out edges.
     *
     * @return Generator|DocumentGraphEdge[]
     */
    public function getIterator(): Generator
    {
        yield from $this->outEdges;
    }

    public function count(): int
    {
        return count($this->inEdges);
    }
}
