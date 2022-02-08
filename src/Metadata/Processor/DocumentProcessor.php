<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Metadata\Processor;

use Doctrine\Inflector\Rules\English\InflectorFactory;
use Elastica\Type;
use Kcs\Metadata\Loader\Processor\Annotation\Processor;
use Kcs\Metadata\Loader\Processor\ProcessorInterface;
use Kcs\Metadata\MetadataInterface;
use Refugis\ODM\Elastica\Annotation\Document;
use Refugis\ODM\Elastica\Exception\InvalidArgumentException;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

use function class_exists;
use function explode;
use function Safe\sprintf;
use function strpos;
use function trigger_error;

use const E_USER_DEPRECATED;

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
        $metadata->collectionName = $subject->collection ?? $this->calculateType($metadata->getReflectionClass()->getShortName());
        $metadata->isReadOnly = $subject->readOnly ?? false;

        $sepIdx = strpos($metadata->collectionName, '/');
        if ($sepIdx === false && class_exists(Type::class)) {
            $metadata->collectionName .= '/' . $metadata->collectionName;
        } elseif ($sepIdx !== false && ! class_exists(Type::class)) {
            $errorMessage = sprintf('Types are not supported in Elasticsearch 7. Please remove the type name from Document annotation or attribute on document class %s', $metadata->name);

            [$index, $type] = explode('/', $metadata->collectionName) + [null, null];
            if ($index !== $type) {
                throw new InvalidArgumentException($errorMessage);
            }

            trigger_error($errorMessage, E_USER_DEPRECATED);
            $metadata->collectionName = $index;
        }

        $metadata->customRepositoryClassName = $subject->repositoryClass;
        if ($subject->joinType === null) {
            return;
        }

        $join = [
            'type' => $subject->joinType,
            'fieldName' => $subject->joinFieldName ?? 'joinField',
        ];

        $metadata->join = $join;
    }

    /**
     * Build a collection name from class name.
     */
    private function calculateType(string $name): string
    {
        static $inflector = null;
        if ($inflector === null) {
            $inflector = (new InflectorFactory())->build();
        }

        $indexName = $inflector->tableize($name);
        if (class_exists(Type::class)) {
            return "$indexName/$indexName";
        }

        return $indexName;
    }
}
