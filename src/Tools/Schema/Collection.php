<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tools\Schema;

use Elastica\Mapping;
use Elastica\Type\Mapping as TypeMapping;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

class Collection
{
    private DocumentMetadata $documentMetadata;

    /** @var TypeMapping|Mapping */
    private object $mapping;

    public function __construct(DocumentMetadata $documentMetadata, object $mapping)
    {
        $this->documentMetadata = $documentMetadata;
        $this->mapping = $mapping;
    }

    public function getDocumentMetadata(): DocumentMetadata
    {
        return $this->documentMetadata;
    }

    /** @return TypeMapping|Mapping */
    public function getMapping()
    {
        return $this->mapping;
    }
}
