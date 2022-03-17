<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tools;

use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Tools\Schema\Collection;
use Refugis\ODM\Elastica\Tools\Schema\Schema;

use function assert;

final class SchemaGenerator
{
    private DocumentManagerInterface $documentManager;

    public function __construct(DocumentManagerInterface $documentManager)
    {
        $this->documentManager = $documentManager;
    }

    public function generateSchema(): Schema
    {
        $schema = new Schema();
        $mappingGenerator = new MappingGenerator($this->documentManager->getTypeManager(), $this->documentManager->getMetadataFactory());
        $factory = $this->documentManager->getMetadataFactory();

        foreach ($factory->getAllMetadata() as $metadata) {
            assert($metadata instanceof DocumentMetadata);
            if ($metadata->discriminatorField !== null && $metadata->getReflectionClass()->getParentClass() !== false) {
                continue;
            }

            $mapping = $mappingGenerator->getMapping($metadata);
            $schema->addCollection(new Collection($metadata, $mapping));
        }

        return $schema;
    }
}
