<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Collection;

use Elastica\Bulk;
use Elastica\Bulk\Response as BulkResponse;
use Elastica\Bulk\ResponseSet;
use Elastica\Client;
use Elastica\Exception\InvalidException;
use Elastica\Exception\ResponseException;
use Elastica\Index;
use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet;
use Elastica\Scroll;
use Elastica\SearchableInterface;
use Elastica\Type;
use Elasticsearch\Endpoints;
use Elasticsearch\Serializers\ArrayToJSONSerializer;
use Refugis\ODM\Elastica\Annotation\Version;
use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Exception\BadResponseException;
use Refugis\ODM\Elastica\Exception\CannotDropAnAliasException;
use Refugis\ODM\Elastica\Exception\IndexNotFoundException;
use Refugis\ODM\Elastica\Exception\ResponseException as ODMResponseException;
use Refugis\ODM\Elastica\Exception\VersionConflictException;
use Refugis\ODM\Elastica\Search\Search;

use function array_filter;
use function array_key_first;
use function array_merge;
use function count;
use function implode;
use function in_array;
use function is_array;
use function Safe\preg_match;
use function strlen;

class Collection implements CollectionInterface
{
    private string $documentClass;
    private SearchableInterface $searchable;
    private ?string $_lastInsertId;
    private string $name;
    private ?string $discriminatorField;
    private ?string $discriminatorValue;

    /** @var array<string, mixed> */
    private array $dynamicSettings;

    /** @var array<string, mixed> */
    private array $staticSettings;

    public function __construct(string $documentClass, SearchableInterface $searchable)
    {
        $this->documentClass = $documentClass;
        $this->searchable = $searchable;
        $this->dynamicSettings = [];
        $this->staticSettings = [];
        $this->discriminatorField = null;
        $this->discriminatorValue = null;

        if ($searchable instanceof Type) {
            $this->name = $searchable->getIndex()->getName() . '/' . $searchable->getName();
        } elseif ($searchable instanceof Index) {
            $this->name = $searchable->getName();
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the index dynamic settings.
     *
     * @param array<string, mixed> $dynamicSettings
     */
    public function setDynamicSettings(array $dynamicSettings): void
    {
        $this->dynamicSettings = $dynamicSettings;
    }

    /**
     * Sets the index static settings.
     *
     * @param array<string, mixed> $staticSettings
     */
    public function setStaticSettings(array $staticSettings): void
    {
        $this->staticSettings = $staticSettings;
    }

    public function setDiscriminator(string $field, string $value): void
    {
        $this->discriminatorField = $field;
        $this->discriminatorValue = $value;
    }

    public function scroll(Query $query, string $expiryTime = '1m'): Scroll
    {
        $query = $this->prepareQuery($query);

        // Scroll requests have optimizations that make them faster when the sort order is _doc.
        // Add it to the query if no sort option have been defined.
        if (! $query->hasParam('sort')) {
            $query->setSort(['_doc']);
        }

        try {
            return $this->searchable->createSearch($query)->scroll($expiryTime);
        } catch (ResponseException $exception) {
            $response = $exception->getResponse();
        }

        if ($response->getStatus() === 404 && $response->getFullError()['type'] === 'index_not_found_exception' ?? null) {
            throw new IndexNotFoundException($response, $response->getFullError()['index'], 'Index not found: ' . $response->getErrorMessage());
        }

        throw new BadResponseException($response);
    }

    public function search(Query $query): ResultSet
    {
        $query = $this->prepareQuery($query);

        try {
            return $this->searchable->search($query);
        } catch (ResponseException $exception) {
            $response = $exception->getResponse();
        }

        if ($response->getStatus() === 404 && $response->getFullError()['type'] === 'index_not_found_exception' ?? null) {
            throw new IndexNotFoundException($response, $response->getFullError()['index'], 'Index not found: ' . $response->getErrorMessage());
        }

        throw new BadResponseException($response);
    }

    public function createSearch(DocumentManagerInterface $documentManager, Query $query): Search
    {
        $search = new Search($documentManager, $this->documentClass, $this);
        $search->setQuery($this->prepareQuery($query));

        return $search;
    }

    public function count(Query $query): int
    {
        return $this->searchable->count($this->prepareQuery($query));
    }

    public function refresh(): void
    {
        $endpoint = new Endpoints\Indices\Refresh();

        try {
            $this->searchable->requestEndpoint($endpoint);
        } catch (ResponseException $exception) {
            $response = $exception->getResponse();
            if ($response->getStatus() === 404 && $response->getFullError()['type'] === 'index_not_found_exception' ?? null) {
                throw new IndexNotFoundException($response, $response->getFullError()['index'], 'Index not found: ' . $response->getErrorMessage());
            }

            throw new ODMResponseException($response, $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function bulk(array $operations): Bulk\ResponseSet
    {
        $body = '';
        $serializer = new ArrayToJSONSerializer();
        $bulkResponses = [];
        $exception = null;
        $startIndex = 0;

        $sendRequest = function (string $body, int $startIndex) use (&$operations, &$bulkResponses, &$exception): void {
            if ($body === '') {
                return;
            }

            $endpoint = new Endpoints\Bulk(new ArrayToJSONSerializer());
            $endpoint->setBody($body);

            try {
                $response = $this->getClient()->requestEndpoint($endpoint);
            } catch (ResponseException $exception) {
                $response = $exception->getResponse();
                if ($response->getStatus() === 413) {
                    throw new ODMResponseException($response, 'Server responded with HTTP 413 Payload too large');
                }
            }

            $data = $response->getData();

            if (! isset($data['items']) || ! is_array($data['items'])) {
                return;
            }

            foreach ($data['items'] as $key => $item) {
                $key += $startIndex;
                if (! isset($operations[$key])) {
                    throw new InvalidException('No response found for action #' . $key);
                }

                $action = $operations[$key];
                $opType = array_key_first($item);
                $bulkResponseData = $item[$opType];

                $bulkResponse = new BulkResponse($bulkResponseData, $action, $opType);
                if (! $bulkResponse->isOk()) {
                    if (($bulkResponseData['status'] ?? null) === 404 && ($bulkResponse->getFullError()['type'] ?? null) === 'index_not_found_exception') {
                        $exception = static fn (Response $r) => new IndexNotFoundException($r, $bulkResponse->getFullError()['index'], 'Index not found: ' . $bulkResponse->getErrorMessage());
                    }

                    if (($bulkResponseData['status'] ?? null) === 409 && ($bulkResponse->getFullError()['type'] ?? null) === 'version_conflict_engine_exception') {
                        $exception ??= static fn (Response $r) => new VersionConflictException($r, 'Version conflict: ' . $bulkResponse->getErrorMessage());
                    }

                    $exception ??= static function (Response $r): void {
                        throw new BadResponseException($r);
                    };
                }

                $bulkResponses[] = $bulkResponse;
            }
        };

        foreach ($operations as $key => $action) {
            $meta = $action->getMetadata();
            if ($this->searchable instanceof Type) {
                $action->setType($this->searchable->getName());
                if (empty($meta['_index'])) {
                    $action->setIndex($this->searchable->getIndex()->getName());
                }
            } elseif (empty($meta['_index']) && $this->searchable instanceof Index) {
                $action->setIndex($this->searchable->getName());
            }

            foreach ($action->toArray() as $data) {
                $body .= $serializer->serialize($data) . "\n";
            }

            if (strlen($body) <= 8 * 1024 * 1024) {
                continue;
            }

            $sendRequest($body, $startIndex);
            $startIndex = $key + 1;
            $body = '';
        }

        $sendRequest($body, $startIndex);
        $bulkResponseSet = new ResponseSet(new Response([]), $bulkResponses);
        if ($exception !== null) {
            throw $exception($bulkResponseSet);
        }

        if ($bulkResponseSet->hasError()) {
            throw new ODMResponseException($bulkResponseSet, 'Response has errors: ' . $bulkResponseSet->getErrorMessage());
        }

        if ($bulkResponseSet->getStatus() >= 400) {
            throw new BadResponseException($bulkResponseSet);
        }

        return $bulkResponseSet;
    }

    /**
     * {@inheritDoc}
     */
    public function create(?string $id, array $body, array $options = []): Response
    {
        $endpoint = new Endpoints\Index();
        $params = [];
        if (! empty($id)) {
            if (
                ! isset($options['version']) ||
                ! in_array($options['version_type'] ?? Version::INTERNAL, [Version::EXTERNAL, Version::EXTERNAL_GTE], true)
            ) {
                $params['op_type'] = 'create';
            }

            $endpoint->setID($id);
        }

        if (isset($options['routing'])) {
            $params['routing'] = $options['routing'];
        }

        if (! empty($params)) {
            $endpoint->setParams($params);
        }

        if (isset($options['index'])) {
            $endpoint->setIndex($options['index']);
        } elseif ($this->searchable instanceof Type) {
            $endpoint->setType($this->searchable->getName());
            $endpoint->setIndex($this->searchable->getIndex()->getName());
        } else {
            $endpoint->setIndex($this->searchable->getName());
        }

        $endpoint->setBody($body);

        try {
            $response = $this->getClient()->requestEndpoint($endpoint);
        } catch (ResponseException $exception) {
            $response = $exception->getResponse();
        }

        $data = $response->getData();
        if (! $response->isOk()) {
            if ($response->getStatus() === 404 && ($response->getFullError()['type'] ?? null) === 'index_not_found_exception') {
                throw new IndexNotFoundException($response, $response->getFullError()['index'], 'Index not found: ' . $response->getErrorMessage());
            }

            throw new BadResponseException($response);
        }

        $this->_lastInsertId = $data['_id'] ?? null;

        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function update(string $id, array $body, string $script = '', array $options = []): void
    {
        $body = array_filter([
            'doc' => $body,
            'script' => $script,
        ]);

        if (count($body) > 1) {
            $tmp = [$script];
            $params = [];

            $i = 0;
            foreach ($body['doc'] as $idx => $value) {
                $paramName = 'p_' . $idx . '_' . ++$i;
                $tmp[] = 'ctx._source.' . $idx . ' = params.' . $paramName;
                $params[$paramName] = $value;
            }

            $script = implode('; ', $tmp) . ';';
            $body = [
                'script' => [
                    'source' => $script,
                    'params' => $params,
                ],
            ];
        }

        $endpoint = new Endpoints\Update();
        $endpoint->setID($id);
        $endpoint->setBody($body);

        $endpointParams = array_filter([
            'routing' => $options['routing'] ?? null,
            'if_seq_no' => $options['seq_no'] ?? null,
            'if_primary_term' => $options['primary_term'] ?? null,
        ], static fn ($value) => $value !== null);

        if ($endpointParams) {
            $endpoint->setParams($endpointParams);
        }

        if ($this->searchable instanceof Type) {
            $endpoint->setType($this->searchable->getName());
            $endpoint->setIndex($this->searchable->getIndex()->getName());
        }

        if (! empty($options['index'])) {
            $endpoint->setIndex($options['index']);
        } elseif ($this->searchable instanceof Index) {
            $endpoint->setIndex($this->searchable->getName());
        }

        try {
            $response = $this->getClient()->requestEndpoint($endpoint);
        } catch (ResponseException $exception) {
            $response = $exception->getResponse();
        }

        if (! $response->isOk()) {
            if ($response->getStatus() === 409 && ($response->getFullError()['type'] ?? null) === 'version_conflict_engine_exception') {
                throw new VersionConflictException($response, 'Version conflict: ' . $response->getErrorMessage());
            }

            throw new BadResponseException($response);
        }
    }

    /**
     * @param array<string, mixed> $options
     * @phpstan-param array{index?: string} $options
     */
    public function delete(string $id, array $options = []): void
    {
        $endpoint = new Endpoints\Delete();
        $endpoint->setID($id);

        if ($this->searchable instanceof Type) {
            $endpoint->setType($this->searchable->getName());
            $endpoint->setIndex($this->searchable->getIndex()->getName());
        }

        if (! empty($options['index'])) {
            $endpoint->setIndex($options['index']);
        } elseif ($this->searchable instanceof Index) {
            $endpoint->setIndex($this->searchable->getName());
        }

        try {
            $response = $this->getClient()->requestEndpoint($endpoint);
        } catch (ResponseException $exception) {
            $response = $exception->getResponse();
        }

        if (! $response->isOk()) {
            throw new BadResponseException($response);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByQuery(Query\AbstractQuery $query, array $params = []): void
    {
        $q = $this->prepareQuery(new Query($query));
        $endpoint = new Endpoints\DeleteByQuery();
        $endpoint->setBody([
            'query' => $q->getQuery()->toArray(),
        ]);

        try {
            $response = $this->searchable->requestEndpoint($endpoint);
        } catch (ResponseException $exception) {
            $response = $exception->getResponse();
        }

        if (! $response->isOk()) {
            if ($response->getStatus() === 404 && $response->getFullError()['type'] === 'index_not_found_exception' ?? null) {
                throw new IndexNotFoundException($response, $response->getFullError()['index'], 'Index not found: ' . $response->getErrorMessage());
            }

            throw new BadResponseException($response);
        }
    }

    public function getLastInsertedId(): ?string
    {
        return $this->_lastInsertId;
    }

    public function updateMapping(object $mapping): void
    {
        $index = $this->searchable;
        if ($index instanceof Type) {
            $index = $index->getIndex();
        }

        if (! $index->exists()) {
            $options = [];
            $settings = array_merge($this->staticSettings, $this->dynamicSettings);
            if (! empty($settings)) {
                $options['settings'] = $settings;
            }

            $index->create($options);
        } elseif (! empty($this->dynamicSettings)) {
            $index->setSettings($this->dynamicSettings);
        }

        try {
            $response = $this->searchable->setMapping($mapping);
        } catch (ResponseException $exception) {
            $response = $exception->getResponse();
        }

        if (! $response->isOk()) {
            throw new BadResponseException($response);
        }
    }

    public function drop(): void
    {
        $index = $this->searchable;
        if ($index instanceof Type) {
            $index = $index->getIndex();
        }

        try {
            $index->delete();
        } catch (ResponseException $exception) {
            $response = $exception->getResponse();

            if ($response->getStatus() === 400 && preg_match('/The provided expression \[.+\] matches an alias/', $response->getErrorMessage())) {
                throw new CannotDropAnAliasException($response, $index->getName(), $exception);
            }

            if ($response->getStatus() !== 404) {
                throw $exception;
            }
        }
    }

    private function prepareQuery(Query $query): Query
    {
        $query = clone $query;
        $query->setParam('seq_no_primary_term', true);
        $query->setParam('version', true);

        if ($this->discriminatorField !== null) {
            $innerQuery = (new Query\BoolQuery())
                ->addFilter(new Query\Term([$this->discriminatorField => ['value' => $this->discriminatorValue]]))
                ->addMust($query->getQuery());

            $query->setQuery($innerQuery);
        }

        return $query;
    }

    private function getClient(): Client
    {
        if ($this->searchable instanceof Type) {
            return $this->searchable->getIndex()->getClient();
        }

        return $this->searchable->getClient();
    }
}
