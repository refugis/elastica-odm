<?php declare(strict_types=1);

namespace Fazland\ODM\Elastica\Type;

use Doctrine\Common\Persistence\ManagerRegistry;

abstract class AbstractDoctrineType extends AbstractType
{
    /**
     * @var ManagerRegistry
     */
    private $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function toPHP($value, array $options = [])
    {
        if (empty($value)) {
            return null;
        }

        if (! isset($options['class'])) {
            throw new \InvalidArgumentException('Missing object fully qualified name.');
        }

        $om = $this->registry->getManagerForClass($options['class']);

        return $om->find($options['class'], $value);
    }

    /**
     * {@inheritdoc}
     */
    public function toDatabase($value, array $options = [])
    {
        if (empty($value)) {
            return null;
        }

        if (! isset($options['class'])) {
            throw new \InvalidArgumentException('Missing object fully qualified name.');
        }

        $om = $this->registry->getManagerForClass($options['class']);
        $class = $om->getClassMetadata($options['class']);

        if (count($class->getIdentifier()) === 1) {
            $id = array_values($class->getIdentifierValues($value))[0];
        } else {
            $id = $class->getIdentifierValues($value);
        }

        return $id;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return static::NAME;
    }
}
