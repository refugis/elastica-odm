<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Mocks;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\EventManager;
use Kcs\Metadata\Loader\Processor\ProcessorFactory;
use ProxyManager\Factory\LazyLoadingGhostFactory;
use Refugis\ODM\Elastica\Collection\DatabaseInterface;
use Refugis\ODM\Elastica\Configuration;
use Refugis\ODM\Elastica\DocumentManager;
use Refugis\ODM\Elastica\Metadata\Loader\AnnotationLoader;
use Refugis\ODM\Elastica\Metadata\Loader\AttributesLoader;
use Refugis\ODM\Elastica\Metadata\MetadataFactory;
use Refugis\ODM\Elastica\UnitOfWork;

class DocumentManagerMock extends DocumentManager
{
    /**
     * @var UnitOfWork|null
     */
    private $uowMock;

    /**
     * @var LazyLoadingGhostFactory|null
     */
    private $proxyFactoryMock;

    /**
     * {@inheritdoc}
     */
    public function getUnitOfWork(): UnitOfWork
    {
        return $this->uowMock ?? parent::getUnitOfWork();
    }

    /**
     * {@inheritdoc}
     */
    public function getProxyFactory(): LazyLoadingGhostFactory
    {
        return $this->proxyFactoryMock ?? parent::getProxyFactory();
    }

    /* Mock API */

    /**
     * Sets a (mock) UnitOfWork that will be returned when getUnitOfWork() is called.
     */
    public function setUnitOfWork(?UnitOfWork $uowMock): void
    {
        $this->uowMock = $uowMock;
    }

    /**
     * @param LazyLoadingGhostFactory|null $proxyFactoryMock
     */
    public function setProxyFactory($proxyFactoryMock): void
    {
        $this->proxyFactoryMock = $proxyFactoryMock;
    }

    /**
     * Mock factory method to create a DocumentManager.
     */
    public static function create(DatabaseInterface $database, Configuration $config = null, EventManager $eventManager = null)
    {
        if (null === $config) {
            $processorFactory = new ProcessorFactory();
            $processorFactory->registerProcessors(__DIR__.'/../../src/Metadata/Processor');

            if (PHP_VERSION_ID >= 80000) {
                $loader = new AnnotationLoader($processorFactory, __DIR__.'/../Fixtures/Document');
                $loader->setReader(new AnnotationReader());
            } else {
                $loader = new AttributesLoader($processorFactory, __DIR__.'/../Fixtures/Document');
            }

            $config = new Configuration();
            $config->setProxyFactory(new LazyLoadingGhostFactory());
            $config->setMetadataFactory(new MetadataFactory($loader));
        }

        if (null === $eventManager) {
            $eventManager = new EventManager();
        }

        return new self($database, $config, $eventManager);
    }
}
