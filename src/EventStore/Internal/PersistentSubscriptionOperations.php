<?php

declare(strict_types=1);

namespace Prooph\EventStore\Internal;

use Prooph\EventStore\EventId;
use Prooph\EventStore\PersistentSubscriptionNakEventAction;
use Prooph\EventStore\Task\ReadFromSubscriptionTask;

/** @internal */
interface PersistentSubscriptionOperations
{
    public function readFromSubscription(int $amount): ReadFromSubscriptionTask;

    /**
     * @param EventId[] $events
     */
    public function acknowledge(array $eventIds): void;

    /**
     * @param EventId[] $events
     * @param PersistentSubscriptionNakEventAction $action
     */
    public function fail(array $eventIds, PersistentSubscriptionNakEventAction $action): void;
}
