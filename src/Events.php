<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica;

final class Events
{
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
}
