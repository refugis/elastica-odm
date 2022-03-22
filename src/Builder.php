<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica;

use Composer\InstalledVersions;
use Elastica\Client;
use ProxyManager\Factory\LazyLoadingGhostFactory;
use Psr\Log\LoggerInterface;
use Refugis\ODM\Elastica\Collection\Database;
use Refugis\ODM\Elastica\Exception\InvalidArgumentException;
use Refugis\ODM\Elastica\Metadata\Loader;
use Refugis\ODM\Elastica\Metadata\MetadataFactory;
use Refugis\ODM\Elastica\Platform\ES6Platform;
use Refugis\ODM\Elastica\Platform\Platform;
use Refugis\ODM\Elastica\Platform\PlatformInterface;
use Refugis\ODM\Elastica\Transport\Transport;
use Refugis\ODM\Elastica\Type\TypeInterface;
use Refugis\ODM\Elastica\Type\TypeManager;

use function array_filter;
use function assert;
use function parse_url;
use function version_compare;

final class Builder
{
    private ?Client $client = null;
    private string $connectionUrl;
    private int $timeout;
    private int $connectTimeout;
    private ?LoggerInterface $logger = null;
    private ?LazyLoadingGhostFactory $proxyFactory = null;
    private ?MetadataFactory $metadataFactory = null;
    private ?PlatformInterface $platform = null;
    private TypeManager $typeManager;
    private bool $addDefaultTypes = true;
    private ?Loader\LoaderInterface $metadataLoader = null;
    private bool $insecure = false;
    private bool $awsAuth = false;

    public static function create(): self
    {
        return new self();
    }

    public function __construct()
    {
        $this->connectionUrl = 'http://localhost:9200/';
        $this->timeout = 30;
        $this->connectTimeout = 5;
        $this->typeManager = new TypeManager();
    }

    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function setConnectionUrl(string $connectionUrl): self
    {
        $this->connectionUrl = $connectionUrl;

        return $this;
    }

    public function setTimeout(int $timeout, int $connectTimeout = 5): self
    {
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;

        return $this;
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function setProxyFactory(LazyLoadingGhostFactory $proxyFactory): self
    {
        $this->proxyFactory = $proxyFactory;

        return $this;
    }

    public function setMetadataFactory(MetadataFactory $metadataFactory): self
    {
        $this->metadataFactory = $metadataFactory;

        return $this;
    }

    public function setPlatform(PlatformInterface $platform): self
    {
        $this->platform = $platform;

        return $this;
    }

    public function addType(TypeInterface $type): self
    {
        $this->addDefaultTypes = false;
        $this->typeManager->addType($type);

        return $this;
    }

    public function addDefaultTypes(): self
    {
        return $this
            ->addType(new Type\BinaryType())
            ->addType(new Type\BooleanType())
            ->addType(new Type\CompletionType())
            ->addType(new Type\DateTimeImmutableType())
            ->addType(new Type\DateTimeType())
            ->addType(new Type\FlattenedType())
            ->addType(new Type\FloatType())
            ->addType(new Type\GeoPointType())
            ->addType(new Type\GeoShapeType())
            ->addType(new Type\IntegerType())
            ->addType(new Type\IpType())
            ->addType(new Type\PercolatorType())
            ->addType(new Type\StringType())
            ->addType(new Type\RawType());
    }

    public function addMetadataLoader(Loader\LoaderInterface $loader): self
    {
        if ($this->metadataLoader === null) {
            $this->metadataLoader = $loader;
        } elseif ($this->metadataLoader instanceof Loader\ChainLoader) {
            $this->metadataLoader->addLoader($loader);
        } else {
            $this->metadataLoader = new Loader\ChainLoader([$this->metadataLoader, $loader]);
        }

        return $this;
    }

    public function allowInsecureConnection(bool $allow = true): self
    {
        $this->insecure = $allow;

        return $this;
    }

    public function enableAwsAuth(bool $enable = true): self
    {
        $this->awsAuth = $enable;

        return $this;
    }

    public function build(): DocumentManager
    {
        if ($this->client === null) {
            $this->client = new Client(self::buildUrl($this->connectionUrl) + [
                'connectTimeout' => $this->connectTimeout,
                'timeout' => $this->timeout,
                'transport' => new Transport([
                    'insecure' => $this->insecure,
                    'aws_auth_v4' => $this->awsAuth,
                ]),
            ], null, $this->logger);
        }

        if ($this->proxyFactory === null) {
            $this->proxyFactory = new LazyLoadingGhostFactory();
        }

        if ($this->metadataFactory === null) {
            if ($this->metadataLoader === null) {
                throw new InvalidArgumentException('You must define at least one metadata loader');
            }

            $this->metadataFactory = new MetadataFactory($this->metadataLoader);
        }

        if ($this->addDefaultTypes) {
            $this->addDefaultTypes();
        }

        $configuration = new Configuration();
        $configuration->setMetadataFactory($this->metadataFactory);
        $configuration->setProxyFactory($this->proxyFactory);
        $configuration->setTypeManager($this->typeManager);

        $platform = $this->platform;
        if ($platform === null) {
            $version = InstalledVersions::getVersion('ruflin/elastica');
            assert($version !== null);

            $platform = version_compare($version, '7.0', '<') ? new ES6Platform() : new Platform();
        }

        $configuration->setPlatform($platform);

        return new DocumentManager(new Database($this->client), $configuration);
    }

    /**
     * @return array<string, string>
     */
    private static function buildUrl(string $url): array
    {
        $url = @parse_url($url);
        if ($url === false) {
            throw new InvalidArgumentException('Malformed URL');
        }

        if (! isset($url['path'])) {
            $url['path'] = '/';
        }

        if (! isset($url['scheme'])) {
            $url['scheme'] = 'http';
        }

        if (! isset($url['host'])) {
            $url['host'] = 'localhost';
            $url['port'] = '9200';
        }

        $authority = $url['user'] ?? '';
        if ($authority && ! empty($url['pass'])) {
            $authority .= ':' . $url['pass'];
        }

        $authority = $authority ? $authority . '@' : '';
        $port = ! empty($url['port']) ? ':' . $url['port'] : '';

        return array_filter([
            'url' => $url['scheme'] . '://' . $authority . $url['host'] . $port . $url['path'] . (! empty($url['query']) ? '?' . $url['query'] : ''),
            'username' => $url['user'] ?? null,
            'password' => $url['pass'] ?? null,
        ]);
    }
}
