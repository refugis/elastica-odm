<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Transport;

use Aws\Credentials\CredentialProvider;
use Aws\Credentials\Credentials;
use Aws\Signature\SignatureV4;
use Elastica\Connection;
use Elastica\Exception\Connection\GuzzleException;
use Elastica\Exception\PartialShardFailureException;
use Elastica\Exception\ResponseException;
use Elastica\JSON;
use Elastica\Request;
use Elastica\Response;
use Elastica\Transport\AwsAuthV4;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

use function class_exists;
use function getenv;
use function is_array;
use function method_exists;
use function microtime;

use const JSON_UNESCAPED_UNICODE;

class Transport extends AwsAuthV4
{
    private const DEFAULT_OPTIONS = [
        'aws_auth_v4' => false,
        'insecure' => false,
    ];

    /** @var array<string, mixed> */
    private array $options;

    /** @param array<string, mixed> $options */
    public function __construct(array $options = self::DEFAULT_OPTIONS)
    {
        parent::__construct();

        $this->options = $options;
    }

    /**
     * {@inheritDoc}
     */
    public function exec(Request $request, array $params): Response
    {
        $connection = $this->getConnection();
        $client = $this->_getGuzzleClient($connection->isPersistent());

        $options = [
            'base_uri' => $this->_getBaseUrl($connection),
            RequestOptions::HEADERS => [
                'Content-Type' => $request->getContentType(),
            ],
            RequestOptions::HTTP_ERRORS => false, // 4xx and 5xx is expected and NOT an exceptions in this context
        ];

        if ($connection->getTimeout()) {
            $options[RequestOptions::TIMEOUT] = $connection->getTimeout();
        }

        $proxy = $connection->getProxy();
        if ($proxy !== null) {
            $options[RequestOptions::PROXY] = $proxy;
        }

        $req = $this->_createPsr7Request($request, $connection);

        try {
            $start = microtime(true);
            $res = $client->send($req, $options);
            $end = microtime(true);
        } catch (TransferException $ex) {
            throw new GuzzleException($ex, $request, new Response($ex->getMessage()));
        }

        $responseBody = (string) $res->getBody();
        $response = new Response($responseBody, $res->getStatusCode());
        $response->setQueryTime($end - $start);
        if ($connection->hasConfig('bigintConversion')) {
            $response->setJsonBigintConversion((bool) $connection->getConfig('bigintConversion'));
        }

        $response->setTransferInfo(
            [
                'request_header' => $request->getMethod(),
                'http_code' => $res->getStatusCode(),
            ],
        );

        if ($response->hasError()) {
            throw new ResponseException($request, $response);
        }

        if ($response->hasFailedShards()) {
            throw new PartialShardFailureException($request, $response);
        }

        return $response;
    }

    /** @param bool $persistent */
    protected function _getGuzzleClient($persistent = true): Client // phpcs:ignore
    {
        if (! $persistent || ! self::$_guzzleClientConnection) {
            if (method_exists(Utils::class, 'chooseHandler')) {
                $stack = HandlerStack::create(Utils::chooseHandler());
            } else {
                $stack = HandlerStack::create(GuzzleHttp\choose_handler()); /** @phpstan-ignore-line */
            }

            if ($this->options['aws_auth_v4'] ?? false) {
                $stack->push($this->getSigningMiddleware(), 'sign');
            }

            self::$_guzzleClientConnection = new Client([
                'handler' => $stack,
                'verify'  => ! ($this->options['insecure'] ?? false),
            ]);
        }

        return self::$_guzzleClientConnection;
    }

    protected function _createPsr7Request(Request $request, Connection $connection): Psr7\Request // phpcs:ignore
    {
        $req = new Psr7\Request(
            $request->getMethod(),
            $this->_getActionPath($request),
            $connection->hasConfig('headers') && is_array($connection->getConfig('headers'))
                ? $connection->getConfig('headers')
                : [],
        );

        $data = $request->getData();
        if (! empty($data) || $data === '0') { /** @phpstan-ignore-line */
            if ($req->getMethod() === Request::GET) {
                $req = $req->withMethod(Request::POST);
            }

            if ($this->hasParam('postWithRequestBody') && $this->getParam('postWithRequestBody') === true) {
                $request->setMethod(Request::POST);
                $req = $req->withMethod(Request::POST);
            }

            $req = $req->withBody($this->streamFor($data));
        }

        return $req;
    }

    private function getCredentialProvider(): callable
    {
        $connection = $this->getConnection();

        if ($connection->hasParam('aws_credential_provider')) {
            return $connection->getParam('aws_credential_provider');
        }

        if ($connection->hasParam('aws_secret_access_key')) {
            return CredentialProvider::fromCredentials(new Credentials(
                $connection->getParam('aws_access_key_id'),
                $connection->getParam('aws_secret_access_key'),
                $connection->hasParam('aws_session_token')
                    ? $connection->getParam('aws_session_token')
                    : null,
            ));
        }

        return CredentialProvider::defaultProvider();
    }

    private function getSigningMiddleware(): callable
    {
        $region = $this->getConnection()->hasParam('aws_region')
            ? $this->getConnection()->getParam('aws_region')
            : getenv('AWS_REGION');
        $signer = new SignatureV4('es', $region);
        $credProvider = $this->getCredentialProvider();

        return Middleware::mapRequest(static function (RequestInterface $req) use (
            $signer,
            $credProvider
        ) {
            return $signer->signRequest($req, $credProvider()->wait());
        });
    }

    /** @param mixed $data */
    private function streamFor($data): StreamInterface
    {
        if (is_array($data)) {
            $data = JSON::stringify($data, JSON_UNESCAPED_UNICODE);
        }

        if (class_exists(Psr7\Utils::class)) {
            return Psr7\Utils::streamFor($data);
        }

        /** @phpstan-ignore-next-line */
        return Psr7\stream_for($data);
    }
}
