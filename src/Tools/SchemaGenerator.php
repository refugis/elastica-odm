<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tools;

use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Tools\Schema\Collection;
use Refugis\ODM\Elastica\Tools\Schema\Schema;

final class SchemaGenerator
{
    /**
     * @var DocumentManagerInterface
     */
    private $documentManager;

    public function __construct(DocumentManagerInterface $documentManager)
    {
        $this->documentManager = $documentManager;
    }

    public function generateSchema(): Schema
    {
        $schema = new Schema();
        $mappingGenerator = new MappingGenerator($this->documentManager->getTypeManager());
        $factory = $this->documentManager->getMetadataFactory();

        /** @var DocumentMetadata $metadata */
        foreach ($factory->getAllMetadata() as $metadata) {
            $mapping = $mappingGenerator->getMapping($metadata);
            $schema->addCollection(new Collection($metadata, $mapping));
        }

        return $schema;
    }
}
