<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;

use function array_values;
use function count;
use function Safe\sprintf;

abstract class AbstractDoctrineType extends AbstractType
{
    private ManagerRegistry $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritDoc}
     *
     * @phpstan-param array{class?: class-string} $options
     */
    public function toPHP($value, array $options = []): ?object
    {
        if ($value === null) {
            return null;
        }

        if (! isset($options['class'])) {
            throw new InvalidArgumentException('Missing object fully qualified name.');
        }

        $om = $this->registry->getManagerForClass($options['class']);
        if ($om === null) {
            throw new InvalidArgumentException(sprintf('%s is not used in any registered object manager.', $options['class']));
        }

        return $om->find($options['class'], $value['identifier']);
    }

    /**
     * {@inheritDoc}
     *
     * @phpstan-param array{class?: class-string} $options
     */
    public function toDatabase($value, array $options = []): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! isset($options['class'])) {
            throw new InvalidArgumentException('Missing object fully qualified name.');
        }

        $om = $this->registry->getManagerForClass($options['class']);
        if ($om === null) {
            throw new InvalidArgumentException(sprintf('%s is not used in any registered object manager.', $options['class']));
        }

        $class = $om->getClassMetadata($options['class']);
        if (count($class->getIdentifier()) === 1) {
            $id = array_values($class->getIdentifierValues($value))[0];
        } else {
            $id = $class->getIdentifierValues($value);
        }

        return ['identifier' => $id];
    }

    public function getName(): string
    {
        /* @phpstan-ignore-next-line */
        return self::NAME;
    }

    /**
     * {@inheritDoc}
     */
    public function getMappingDeclaration(array $options = []): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'identifier' => ['type' => 'keyword'],
            ],
        ];
    }
}
