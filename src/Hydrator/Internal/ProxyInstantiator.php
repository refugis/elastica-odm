<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Hydrator\Internal;

use Doctrine\Instantiator\InstantiatorInterface;
use ProxyManager\Proxy\GhostObjectInterface;
use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;

use function array_map;
use function assert;
use function in_array;
use function strtolower;

class ProxyInstantiator implements InstantiatorInterface
{
    /** @var string[] */
    private array $fields;
    private DocumentManagerInterface $manager;

    public function __construct(array $fields, DocumentManagerInterface $manager)
    {
        $this->fields = $fields;
        $this->manager = $manager;
    }

    /**
     * {@inheritdoc}
     */
    public function instantiate($className): GhostObjectInterface
    {
        return $this->createProxy($className, $this->fields);
    }

    /**
     * @phpstan-param class-string $className
     */
    private function createProxy(string $className, array $fields): GhostObjectInterface
    {
        $class = $this->manager->getClassMetadata($className);
        assert($class instanceof DocumentMetadata);

        $allowedMethods = array_map(static function (string $field) {
            return strtolower('get' . $field);
        }, $fields);

        $initializer = function (
            GhostObjectInterface $ghostObject,
            string $method,
            array $parameters,
            &$initializer
        ) use (
            $fields,
            $allowedMethods
): bool {
            if (($method === '__get' || $method === '__set') && in_array($parameters['name'], $fields, true)) {
                return false;
            }

            if (in_array(strtolower($method), $allowedMethods, true)) {
                return false;
            }

            $initializer = null;
            $this->manager->refresh($ghostObject);

            return true;
        };

        $skippedProperties = [];
        foreach ($class->attributesMetadata as $field) {
            if (! $field instanceof FieldMetadata) {
                continue;
            }

            if ($field->isStored() && ! in_array($field->getName(), $fields, true)) {
                continue;
            }

            $reflectionProperty = $field->getReflection();

            if ($reflectionProperty->isPrivate()) {
                $skippedProperties[] = "\0{$field->class}\0{$field->name}";
            } elseif ($reflectionProperty->isProtected()) {
                $skippedProperties[] = "\0*\0{$field->name}";
            } else {
                $skippedProperties[] = $field->name;
            }
        }

        $proxyOptions = ['skippedProperties' => $skippedProperties];

        return $this->manager->getProxyFactory()->createProxy($className, $initializer, $proxyOptions);
    }
}
