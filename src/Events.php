<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica;

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase

final class Events
{
    /**
     * The prePersist event occurs for a given document before the collection
     * persist operation for that document is executed.
     * This is a lifecycle event.
     */
    public const prePersist = 'prePersist';

    /**
     * The postPersist event occurs for a document after has been persisted.
     * It will be invoked after the database index operation.
     * Generated id values are available in the postPersist event.
     * This is a lifecycle event.
     */
    public const postPersist = 'postPersist';

    /**
     * The preUpdate event occurs before the collection updates document data.
     * This is a lifecycle event.
     */
    public const preUpdate = 'preUpdate';

    /**
     * The postUpdate event occurs after the collection update operations
     * have been completed.
     * This is a lifecycle event.
     */
    public const postUpdate = 'postUpdate';

    /**
     * The onClear event occurs when the DocumentManager#clear() operation is invoked,
     * after all references to documents have been removed from the unit of work.
     */
    public const onClear = 'onClear';

    /**
     * The preFlush event occurs when the DocumentManager#flush() operation is invoked,
     * but before any changes to managed documents have been calculated.
     */
    public const preFlush = 'preFlush';

    /**
     * The onFlush event occurs when the DocumentManager#flush() operation is invoked,
     * after any changes to managed documents have been determined but before any
     * actual database operations are executed. The event is only raised if there is
     * actually something to do for the underlying UnitOfWork. If nothing needs to be done,
     * the onFlush event is not raised.
     */
    public const onFlush = 'onFlush';

    /**
     * The postFlush event occurs when the DocumentManager#flush() operation is invoked and
     * after all actual database operations are executed successfully. The event is only raised if there is
     * actually something to do for the underlying UnitOfWork. If nothing needs to be done,
     * the postFlush event is not raised. The event won't be raised if an error occurs during the
     * flush operation.
     */
    public const postFlush = 'postFlush';

    /**
     * The onIndexNotFound event occurs when the DocumentManager#flush() operation is invoked,
     * and an index does not exist and action.auto_create_index is set to false.
     */
    public const onIndexNotFound = 'onIndexNotFound';
}
