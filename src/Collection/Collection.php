<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Collection;

use Elastica\Exception\ResponseException;
use Elastica\Index;
use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet;
use Elastica\Scroll;
use Elastica\SearchableInterface;
use Elastica\Type;
use Elasticsearch\Endpoints;
use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Exception\CannotDropAnAliasException;
use Refugis\ODM\Elastica\Exception\IndexNotFoundException;
use Refugis\ODM\Elastica\Exception\RuntimeException;
use Refugis\ODM\Elastica\Search\Search;

use function array_filter;
use function array_merge;
use function count;
use function implode;
use function Safe\preg_match;

class Collection implements CollectionInterface
{
    private string $documentClass;
    private SearchableInterface $searchable;
    private ?string $_lastInsertId;
    private string $name;

    /** @var array<string, mixed> */
    private array $dynamicSettings;

    /** @var array<string, mixed> */
    private array $staticSettings;

    /**
     * @deprecated
     *
     * @var array<string, mixed>
     */
    private array $indexParams;

    public function __construct(string $documentClass, SearchableInterface $searchable)
    {
        $this->documentClass = $documentClass;
        $this->searchable = $searchable;
        $this->indexParams = [];
        $this->dynamicSettings = [];
        $this->staticSettings = [];

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
     * Sets the index params used when the index is created.
     *
     * @deprecated Index params are deprecated and will be removed in 2.0. Please use setDynamicSettings/setStaticSettings instead.
     *
     * @param array<string, mixed> $indexParams
     */
    public function setIndexParams(array $indexParams = []): void
    {
        $this->indexParams = $indexParams;
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

    public function scroll(Query $query, string $expiryTime = '1m'): Scroll
    {
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
            throw new IndexNotFoundException('Index not found: ' . $response->getErrorMessage());
        }

        throw new RuntimeException('Response not OK: ' . $response->getErrorMessage());
    }

    public function search(Query $query): ResultSet
    {
        try {
            return $this->searchable->search($query);
        } catch (ResponseException $exception) {
            $response = $exception->getResponse();
        }

        if ($response->getStatus() === 404 && $response->getFullError()['type'] === 'index_not_found_exception' ?? null) {
            throw new IndexNotFoundException('Index not found: ' . $response->getErrorMessage());
        }

        throw new RuntimeException('Response not OK: ' . $response->getErrorMessage());
    }

    public function createSearch(DocumentManagerInterface $documentManager, Query $query): Search
    {
        $search = new Search($documentManager, $this->documentClass);
        $search->setQuery($query);

        return $search;
    }

    public function count(Query $query): int
    {
        return $this->searchable->count($query);
    }

    public function refresh(): void
    {
        $endpoint = new Endpoints\Indices\Refresh();

        try {
            $this->searchable->requestEndpoint($endpoint);
        } catch (ResponseException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(?string $id, array $body): Response
    {
        $endpoint = new Endpoints\Index();
        if (! empty($id)) {
            $endpoint->setParams(['op_type' => 'create']);
            $endpoint->setID($id);
        }

        $endpoint->setBody($body);
        try {
            $response = $this->searchable->requestEndpoint($endpoint);
        } catch (ResponseException $exception) {
            $response = $exception->getResponse();
        }

        $data = $response->getData();
        if (! $response->isOk()) {
            if ($response->getStatus() === 404 && $response->getFullError()['type'] === 'index_not_found_exception' ?? null) {
                throw new IndexNotFoundException('Index not found: ' . $response->getErrorMessage());
            }

            throw new RuntimeException('Response not OK: ' . $response->getErrorMessage());
        }

        $this->_lastInsertId = $data['_id'] ?? null;

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $id, array $body, string $script = ''): void
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

        try {
            $response = $this->searchable->requestEndpoint($endpoint);
        } catch (ResponseException $exception) {
            $response = $exception->getResponse();
        }

        if (! $response->isOk()) {
            throw new RuntimeException('Response not OK: ' . $response->getErrorMessage());
        }
    }

    public function delete(string $id): void
    {
        $endpoint = new Endpoints\Delete();
        $endpoint->setID($id);

        try {
            $response = $this->searchable->requestEndpoint($endpoint);
        } catch (ResponseException $exception) {
            $response = $exception->getResponse();
        }

        if (! $response->isOk()) {
            throw new \RuntimeException('Response not OK: ' . $response->getErrorMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByQuery(Query\AbstractQuery $query, array $params = []): void
    {
        $endpoint = new Endpoints\DeleteByQuery();
        $endpoint->setBody([
            'query' => $query->toArray(),
        ]);

        try {
            $response = $this->searchable->requestEndpoint($endpoint);
        } catch (ResponseException $exception) {
            $response = $exception->getResponse();
        }

        if (! $response->isOk()) {
            throw new \RuntimeException('Response not OK: ' . $response->getErrorMessage());
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
            $indexParams = $this->indexParams ?? null;
            $indexParams['settings'] = array_merge($indexParams['settings'] ?? [], $this->staticSettings, $this->dynamicSettings);

            $index->create($indexParams);
        } elseif (! empty($this->dynamicSettings)) {
            $index->setSettings($this->dynamicSettings);
        }

        try {
            $response = $this->searchable->setMapping($mapping);
        } catch (ResponseException $exception) {
            $response = $exception->getResponse();
        }

        if (! $response->isOk()) {
            throw new \RuntimeException('Response not OK: ' . $response->getErrorMessage());
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
                throw new CannotDropAnAliasException($index->getName(), $exception);
            }

            if ($response->getStatus() !== 404) {
                throw $exception;
            }
        }
    }
}
