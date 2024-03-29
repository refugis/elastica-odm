<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Collection;

use Elastica\Bulk;
use Elastica\Mapping;
use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet;
use Elastica\Scroll;
use Elastica\Type\Mapping as TypeMapping;
use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Search\Search;

interface CollectionInterface
{
    /**
     * Gets the name of the collection (could be index/type or just index name
     * in case the ES version does not support types any more).
     */
    public function getName(): string;

    /**
     * Executes a search.
     */
    public function search(Query $query): ResultSet;

    /**
     * Executes a scroll search.
     */
    public function scroll(Query $query, string $expiryTime = '1m'): Scroll;

    /**
     * Creates a search object.
     */
    public function createSearch(DocumentManagerInterface $documentManager, Query $query): Search;

    /**
     * Counts document matching query.
     */
    public function count(Query $query): int;

    /**
     * Executes a refresh operation on the index.
     */
    public function refresh(): void;

    /** @param Bulk\Action[] $operations */
    public function bulk(array $operations): Bulk\ResponseSet;

    /**
     * Request the index of a document.
     *
     * @param array<string, mixed> $body
     * @param array<string, mixed> $options
     * @phpstan-param array{routing?: string, sequence_number?: int, primary_term?: int, index?: string} $options
     */
    public function create(?string $id, array $body, array $options = []): Response;

    /**
     * Updates a document.
     *
     * @param array<string, mixed> $body
     * @param array<string, mixed> $options
     * @phpstan-param array{routing?: string, sequence_number?: int, primary_term?: int, index?: string} $options
     */
    public function update(string $id, array $body, string $script = '', array $options = []): void;

    /**
     * Request the deletion of a document.
     *
     * @param array<string, mixed> $options
     * @phpstan-param array{index?: string, type?: string} $options
     */
    public function delete(string $id, array $options = []): void;

    /**
     * Request the deletion of a set of document, matched by the given query.
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-delete-by-query.html
     *      for the limitations of this method and the available parameters.
     *
     * @param array<string, mixed> $params
     */
    public function deleteByQuery(Query\AbstractQuery $query, array $params = []): void;

    /**
     * Returns the last inserted identifier as string.
     */
    public function getLastInsertedId(): ?string;

    /**
     * Updates the collection mapping.
     *
     * @param TypeMapping|Mapping $mapping
     */
    public function updateMapping(object $mapping): void;

    /**
     * Drops the entire collection.
     */
    public function drop(): void;
}
