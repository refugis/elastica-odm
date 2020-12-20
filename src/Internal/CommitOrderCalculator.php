<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Internal;

use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

use function array_filter;
use function array_reverse;
use function in_array;
use function iterator_to_array;

final class CommitOrderCalculator
{
    /** @var DocumentGraph */
    private $graph;

    public function __construct()
    {
        $this->graph = new DocumentGraph();
    }

    /**
     * Adds a document class to the dependency graph
     * and evaluates its associations.
     */
    public function addClass(DocumentMetadata $metadata): void
    {
        $this->graph->addNode($metadata->name);

        $assocNames = $metadata->getAssociationNames();
        foreach ($assocNames as $assocName) {
            $targetClass = $metadata->getAssociationTargetClass($assocName);
            $this->graph->addNode($targetClass);
            $this->graph->connect($metadata->name, $targetClass);
        }
    }

    /**
     * Gets the commit order set.
     *
     * @param array $classNames
     *
     * @return array
     */
    public function getOrder(array $classNames): array
    {
        $elements = array_filter(iterator_to_array($this->graph), static function ($element) use ($classNames): bool {
            [$node] = $element;

            return in_array($node->getClassName(), $classNames, true);
        });

        return array_reverse($elements, false);
    }
}
