<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Id;

use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Util\ClassUtil;

final class IdentityGenerator extends AbstractIdGenerator
{
    /**
     * {@inheritdoc}
     */
    public function generate(DocumentManagerInterface $dm, object $document): ?string
    {
        $collection = $dm->getCollection(ClassUtil::getClass($document));

        return $collection->getLastInsertedId();
    }

    /**
     * {@inheritdoc}
     */
    public function isPostInsertGenerator(): bool
    {
        return true;
    }
}
