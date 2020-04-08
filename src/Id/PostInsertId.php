<?php declare(strict_types=1);

namespace Refugis\ODM\Elastica\Id;

final class PostInsertId
{
    /**
     * The document for which the id has been created.
     *
     * @var object
     */
    private $document;

    /**
     * The returned id.
     *
     * @var string
     */
    private $id;

    public function __construct(object $document, string $id)
    {
        $this->document = $document;
        $this->id = $id;
    }

    /**
     * Gets the document for this id.
     */
    public function getDocument(): object
    {
        return $this->document;
    }

    /**
     * Returns the newly generated id.
     */
    public function getId(): string
    {
        return $this->id;
    }
}
