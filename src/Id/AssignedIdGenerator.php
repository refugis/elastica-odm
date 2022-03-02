<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Id;

use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Exception\InvalidIdentifierException;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Util\ClassUtil;

use function assert;

final class AssignedIdGenerator extends AbstractIdGenerator
{
    public function generate(DocumentManagerInterface $dm, object $document): string
    {
        $class = $dm->getClassMetadata(ClassUtil::getClass($document));
        assert($class instanceof DocumentMetadata);
        $id = $class->getSingleIdentifier($document);

        if ($id === null) {
            throw new InvalidIdentifierException('Document of type "' . $class->name . '" is missing an assigned ID. NONE generator strategy requires the ID field to be populated before persist is called.');
        }

        return $id;
    }
}
