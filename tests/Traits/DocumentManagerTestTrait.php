<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Traits;

use Doctrine\Common\Annotations\AnnotationReader;
use Refugis\ODM\Elastica\Builder;
use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Metadata\Loader\AnnotationLoader;
use Kcs\Metadata\Loader\Processor\ProcessorFactory;

trait DocumentManagerTestTrait
{
    private static function createDocumentManager(): DocumentManagerInterface
    {
        $processorFactory = new ProcessorFactory();
        $processorFactory->registerProcessors(__DIR__.'/../../src/Metadata/Processor');

        $loader = new AnnotationLoader($processorFactory, __DIR__.'/../Fixtures/Document');
        $loader->setReader(new AnnotationReader());

        $builder = Builder::create()->addMetadataLoader($loader);

        if ($endpoint = \getenv('ES_ENDPOINT')) {
            $builder->setConnectionUrl($endpoint);
        }

        return $builder->build();
    }
}
