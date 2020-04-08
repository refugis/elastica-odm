<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Tests\Mocks;

use Refugis\ODM\Elastica\Id\PostInsertId;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Persister\DocumentPersister;

class DocumentPersisterMock extends DocumentPersister
{
    private $inserts = [];
    private $updates = [];
    private $deletes = [];
    private $identityValueCounter = 1;
    private $postInsertIds = [];
    private $generatorType;
    private $existsCalled = false;

    /**
     * {@inheritdoc}
     */
    public function insert($document): ?PostInsertId
    {
        $this->inserts[] = $document;

        if (
            DocumentMetadata::GENERATOR_TYPE_AUTO === $this->generatorType ||
            DocumentMetadata::GENERATOR_TYPE_AUTO === $this->getClassMetadata()->idGeneratorType
        ) {
            return $this->postInsertIds[] = new PostInsertId($document, (string) $this->identityValueCounter++);
        }

        return null;
    }

    public function exists(array $criteria): bool
    {
        $this->existsCalled = true;

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function update($document): void
    {
        $this->updates[] = $document;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($document): void
    {
        $this->deletes[] = $document;
    }

    /**
     * @param int|null $generatorType
     */
    public function setMockGeneratorType(?int $generatorType): void
    {
        $this->generatorType = $generatorType;
    }

    /**
     * @return array
     */
    public function getInserts(): array
    {
        return $this->inserts;
    }

    /**
     * @return array
     */
    public function getUpdates(): array
    {
        return $this->updates;
    }

    /**
     * @return array
     */
    public function getDeletes(): array
    {
        return $this->deletes;
    }

    /**
     * @return bool
     */
    public function isExistsCalled(): bool
    {
        return $this->existsCalled;
    }

    /**
     * Resets mock.
     */
    public function reset(): void
    {
        $this->inserts =
        $this->updates =
        $this->deletes =
        $this->postInsertIds = [];
        $this->generatorType = null;
        $this->existsCalled = false;
    }
}
