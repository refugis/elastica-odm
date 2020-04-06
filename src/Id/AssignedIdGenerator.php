<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Id;

use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Exception\InvalidIdentifierException;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Util\ClassUtil;

final class AssignedIdGenerator extends AbstractIdGenerator
{
    /**
     * {@inheritdoc}
     */
    public function generate(DocumentManagerInterface $dm, $document)
    {
        /** @var DocumentMetadata $class */
        $class = $dm->getClassMetadata(ClassUtil::getClass($document));
        $id = $class->getSingleIdentifier($document);

        if (null === $id) {
            throw new InvalidIdentifierException(
                'Document of type "'.$class->name.'" is missing an assigned ID.'.
                'NONE generator strategy requires the ID field to be populated before persist is called.'
            );
        }

        return $id;
    }
}
