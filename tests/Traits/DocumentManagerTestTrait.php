<?php declare(strict_types=1);

namespace Tests\Traits;

use Doctrine\Common\Annotations\AnnotationReader;
use Refugis\ODM\Elastica\Builder;
use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Metadata\Loader\AnnotationLoader;

use function getenv;

trait DocumentManagerTestTrait
{
    private static function createDocumentManager(): DocumentManagerInterface
    {
        $loader = new AnnotationLoader(AnnotationLoader::createProcessorFactory(), __DIR__.'/../Fixtures/Document');
        $loader->setReader(new AnnotationReader());

        $builder = Builder::create()->addMetadataLoader($loader);
        $builder->allowInsecureConnection();

        if ($endpoint = getenv('ES_ENDPOINT')) {
            $builder->setConnectionUrl($endpoint);
        }

        return $builder->build();
    }
}
