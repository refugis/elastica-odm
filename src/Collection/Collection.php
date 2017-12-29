<?php declare(strict_types=1);

namespace Fazland\ODM\Elastica\Collection;

use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet;
use Elastica\Scroll;
use Elastica\SearchableInterface;
use Elasticsearch\Endpoints;
use Fazland\ODM\Elastica\DocumentManagerInterface;
use Fazland\ODM\Elastica\Search\Search;
use Psr\Cache\CacheItemPoolInterface;

class Collection implements CollectionInterface
{
    /**
     * @var DocumentManagerInterface
     */
    private $documentManager;

    /**
     * @var string
     */
    private $documentClass;

    /**
     * @var SearchableInterface
     */
    private $searchable;

    /**
     * @var CacheItemPoolInterface|null
     */
    private $resultCache;

    /**
     * @var null|string
     */
    private $_lastInsertId;

    public function __construct(DocumentManagerInterface $documentManager, string $documentClass, SearchableInterface $searchable)
    {
        $this->documentManager = $documentManager;
        $this->documentClass = $documentClass;
        $this->searchable = $searchable;
    }

    /**
     * {@inheritdoc}
     */
    public function scroll(Query $query, string $expiryTime = '1m'): Scroll
    {
        // Scroll requests have optimizations that make them faster when the sort order is _doc.
        // Add it to the query if no sort option have been defined.
        if (! $query->hasParam('sort')) {
            $query->setSort(['_doc']);
        }

        return $this->searchable->createSearch($query)->scroll($expiryTime);
    }

    /**
     * {@inheritdoc}
     */
    public function search(Query $query): ResultSet
    {
        return $this->searchable->search($query);
    }

    /**
     * {@inheritdoc}
     */
    public function getResultCache(): ?CacheItemPoolInterface
    {
        return $this->resultCache;
    }

    /**
     * {@inheritdoc}
     */
    public function setResultCache(?CacheItemPoolInterface $resultCache): void
    {
        $this->resultCache = $resultCache;
    }

    /**
     * {@inheritdoc}
     */
    public function createSearch(Query $query): Search
    {
        $search = new Search($this->documentManager, $this->documentClass);
        $search->setQuery($query);

        return $search;
    }

    /**
     * {@inheritdoc}
     */
    public function count(Query $query): int
    {
        return $this->searchable->count($query);
    }

    /**
     * {@inheritdoc}
     */
    public function refresh(): void
    {
        $endpoint = new Endpoints\Indices\Refresh();
        $this->searchable->requestEndpoint($endpoint);
    }

    /**
     * {@inheritdoc}
     */
    public function create(?string $id, array $body): Response
    {
        $endpoint = new Endpoints\Index();
        if (! empty($id)) {
            $endpoint->setID($id);
        }

        $endpoint->setBody($body);
        $response = $this->searchable->requestEndpoint($endpoint);

        $data = $response->getData();
        if (! $response->isOk()) {
            throw new \RuntimeException('Response not OK');
        }

        if (isset($data['_id'])) {
            $this->_lastInsertId = $data['_id'];
        } else {
            $this->_lastInsertId = null;
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $id, array $body): void
    {
        $endpoint = new Endpoints\Update();
        $endpoint->setID($id);

        $endpoint->setBody([
            'doc' => $body,
        ]);

        $response = $this->searchable->requestEndpoint($endpoint);

        if (! $response->isOk()) {
            throw new \RuntimeException('Response not OK');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $id): void
    {
        $endpoint = new Endpoints\Delete();
        $endpoint->setID($id);

        $response = $this->searchable->requestEndpoint($endpoint);

        if (! $response->isOk()) {
            throw new \RuntimeException('Response not OK');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLastInsertedId(): ?string
    {
        return $this->_lastInsertId;
    }
}