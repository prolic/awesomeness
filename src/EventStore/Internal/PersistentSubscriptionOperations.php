<?php

declare(strict_types=1);

namespace Prooph\EventStore\Internal;

use Prooph\EventStore\Data\EventId;
use Prooph\EventStore\Data\EventRecord;
use Prooph\EventStore\Data\PersistentSubscriptionNakEventAction;

/** @internal */
interface PersistentSubscriptionOperations
{
    /**
     * @return EventRecord[]
     */
    public function readFromSubscription(int $amount): array;

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
