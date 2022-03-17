<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Hydrator;

use Doctrine\Instantiator\Instantiator;
use Elastica\Document;
use Elastica\Exception\InvalidException;
use Elastica\ResultSet;
use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Exception\ResponseException;
use Refugis\ODM\Elastica\Hydrator\Internal\ProxyInstantiator;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;

use function assert;
use function Safe\sort;

class ObjectHydrator implements HydratorInterface
{
    private DocumentManagerInterface $manager;

    public function __construct(DocumentManagerInterface $manager)
    {
        $this->manager = $manager;
    }

    /**
     * {@inheritdoc}
     */
    public function hydrateAll(ResultSet $resultSet, string $className): array
    {
        $response = $resultSet->getResponse();
        if (! $response->isOk()) {
            throw new ResponseException($response, 'Response not OK: ' . $response->getErrorMessage());
        }

        if ($resultSet->count() === 0) {
            return [];
        }

        $class = $this->manager->getClassMetadata($className);
        assert($class instanceof DocumentMetadata);
        try {
            $source = $resultSet->getQuery()->getParam('_source');
            sort($source);
        } catch (InvalidException $ex) {
            $source = null;
        }

        $allFields = [...$class->fieldNames, ...$class->embeddedFieldNames];

        sort($allFields);
        if ($source !== null && $source !== $allFields) {
            $fields = array_filter(array_map(static function (string $fieldName) use ($class) {
                if ($class->joinField === $fieldName) {
                    return $class->getParentDocumentField()->name;
                }

                if ($class->discriminatorField === $fieldName) {
                    return null;
                }

                return $fieldName;
            }, $source === false ? [] : $source));
            $instantiator = new ProxyInstantiator($fields, $this->manager);
        } else {
            $instantiator = $this->getInstantiator();
        }

        $results = [];

        foreach ($resultSet as $result) {
            $document = $result->getDocument();
            $object = $this->manager->getUnitOfWork()->tryGetById($document->getId(), $class);
            if ($object === null) {
                $discrField = $class->discriminatorField;
                if ($discrField !== null) {
                    $value = $document->get($discrField);
                    $resultClassName = $class->discriminatorMap[$value];
                } else {
                    $resultClassName = $className;
                }

                if ($resultClassName !== $className) {
                    $fields = array_filter(array_map(static function (string $fieldName) use ($class) {
                        if ($class->joinField === $fieldName) {
                            return $class->getParentDocumentField()->name;
                        }

                        if ($class->discriminatorField === $fieldName) {
                            return null;
                        }

                        return $fieldName;
                    }, array_keys($document->getData())));

                    $instantiator = new ProxyInstantiator($fields, $this->manager);
                }

                $object = $instantiator->instantiate($resultClassName);
                $this->manager->getUnitOfWork()->createDocument($document, $object);
            }

            $results[] = $object;
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function hydrateOne(Document $document, string $className)
    {
        $result = $this->getInstantiator()->instantiate($className);
        $this->manager->getUnitOfWork()->createDocument($document, $result);

        return $result;
    }

    private function getInstantiator(): Instantiator
    {
        static $instantiator = null;
        if ($instantiator === null) {
            $instantiator = new Instantiator();
        }

        return $instantiator;
    }
}
