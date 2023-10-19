<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Type;

use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

use function assert;

/**
 * TODO: Please remove this and handle associations in uow.
 */
final class ESDocumentType extends AbstractType implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public const NAME = 'es_document';

    /**
     * {@inheritDoc}
     *
     * @phpstan-param array{class?: class-string} $options
     */
    public function toPHP($value, array $options = [])
    {
        if (empty($value)) {
            return null;
        }

        if (! isset($options['class'])) {
            throw new InvalidArgumentException('Missing object fully qualified name.');
        }

        $om = $this->container->get(DocumentManagerInterface::class);
        assert($om instanceof DocumentManagerInterface);

        return $om->find($options['class'], $value);
    }

    /**
     * {@inheritDoc}
     */
    public function toDatabase($value, array $options = [])
    {
        // @todo
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * {@inheritDoc}
     */
    public function getMappingDeclaration(array $options = []): array
    {
        return [];
    }
}
