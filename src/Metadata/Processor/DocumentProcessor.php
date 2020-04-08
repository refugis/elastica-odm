<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Doctrine\Common\Inflector\Inflector;
use Elastica\Type;
use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\Document;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

/**
 * @Processor(annotation=Document::class)
 */
class DocumentProcessor implements ProcessorInterface
{
    /**
     * {@inheritdoc}
     *
     * @param DocumentMetadata $metadata
     * @param Document         $subject
     */
    public function process(MetadataInterface $metadata, $subject): void
    {
        $metadata->document = true;

        $metadata->collectionName = $subject->type ?? $this->calculateType($metadata->name);
        if (\class_exists(Type::class) && false === \strpos($metadata->collectionName, '/')) {
            $metadata->collectionName .= '/'.$metadata->collectionName;
        }

        $metadata->customRepositoryClassName = $subject->repositoryClass;
    }

    /**
     * Build a collection name from class name.
     *
     * @param string $name
     *
     * @return string
     */
    private function calculateType(string $name): string
    {
        $indexName = Inflector::tableize($name);
        if (\class_exists(Type::class)) {
            return "$indexName/$indexName";
        }

        return $indexName;
    }
}
