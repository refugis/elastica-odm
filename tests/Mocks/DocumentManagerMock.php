<?php declare(strict_types=1);

namespace Tests\Mocks;

use Composer\InstalledVersions;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\EventManager;
use Kcs\Metadata\Loader\Processor\ProcessorFactory;
use ProxyManager\Factory\LazyLoadingGhostFactory;
use Refugis\ODM\Elastica\Collection\DatabaseInterface;
use Refugis\ODM\Elastica\Configuration;
use Refugis\ODM\Elastica\DocumentManager;
use Refugis\ODM\Elastica\Metadata\Loader\AnnotationLoader;
use Refugis\ODM\Elastica\Metadata\Loader\AttributesLoader;
use Refugis\ODM\Elastica\Metadata\Loader\ChainLoader;
use Refugis\ODM\Elastica\Metadata\MetadataFactory;
use Refugis\ODM\Elastica\Platform\ES6Platform;
use Refugis\ODM\Elastica\Platform\Platform;
use Refugis\ODM\Elastica\Type\StringType;
use Refugis\ODM\Elastica\UnitOfWork;

class DocumentManagerMock extends DocumentManager
{
    private ?UnitOfWork $uowMock;
    private ?LazyLoadingGhostFactory $proxyFactoryMock;

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
            $loader = new AnnotationLoader(AnnotationLoader::createProcessorFactory(), __DIR__.'/../Fixtures/Document');
            $loader->setReader(new AnnotationReader());

            $config = new Configuration();
            $config->setProxyFactory(new LazyLoadingGhostFactory());
            $config->setMetadataFactory(new MetadataFactory($loader));
            $config->getTypeManager()->addType(new StringType());

            $platform = version_compare(InstalledVersions::getVersion('ruflin/elastica'), '7.0', '<') ? new ES6Platform() : new Platform();
            $config->setPlatform($platform);
        }

        if (null === $eventManager) {
            $eventManager = new EventManager();
        }

        return new self($database, $config, $eventManager);
    }
}
