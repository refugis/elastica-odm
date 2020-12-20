<?php declare(strict_types=1);

namespace Tests\Internal;

use PHPUnit\Framework\TestCase;
use Refugis\ODM\Elastica\Internal\DocumentGraph;
use Refugis\ODM\Elastica\Internal\DocumentGraphEdge;
use Refugis\ODM\Elastica\Internal\DocumentGraphNode;
use Tests\Fixtures\Document\Foo;

class DocumentGraphTest extends TestCase
{
    /**
     * @var DocumentGraph
     */
    private $graph;

    protected function setUp(): void
    {
        $this->graph = new DocumentGraph();
    }

    public function testGetNodesReturnsEmptyArrayOnEmptyGraph(): void
    {
        self::assertEquals([], $this->graph->getNodes());
    }

    public function testAddNode(): void
    {
        $this->graph->addNode(\stdClass::class);

        self::assertEquals([
            \stdClass::class => new DocumentGraphNode(\stdClass::class),
        ], $this->graph->getNodes());
    }

    public function testConnect(): void
    {
        $this->graph->addNode(Foo::class);
        $this->graph->addNode(\stdClass::class);

        $this->graph->connect(Foo::class, \stdClass::class);

        $fooNode = new DocumentGraphNode(Foo::class);
        $stdNode = new DocumentGraphNode(\stdClass::class);

        $edge = new DocumentGraphEdge($fooNode, $stdNode);

        $fooNode->addOutEdge($edge);
        $stdNode->addInEdge($edge);

        self::assertEquals([
            Foo::class => $fooNode,
            \stdClass::class => $stdNode,
        ], $this->graph->getNodes());
    }

    public function testIterator(): void
    {
        $this->graph->addNode(Foo::class);
        $this->graph->addNode(\stdClass::class);

        $this->graph->connect(Foo::class, \stdClass::class);

        $fooNode = new DocumentGraphNode(Foo::class);
        $stdNode = new DocumentGraphNode(\stdClass::class);

        $edge = new DocumentGraphEdge($fooNode, $stdNode);

        $fooNode->addOutEdge($edge);
        $stdNode->addInEdge($edge);

        self::assertEquals([
            [$stdNode, 2],
            [$fooNode, 1],
        ], \iterator_to_array($this->graph));
    }
}
