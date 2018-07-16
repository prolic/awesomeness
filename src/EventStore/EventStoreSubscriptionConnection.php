<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Prooph\EventStore\Data\PersistentSubscriptionSettings;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Internal\Data\PersistentSubscriptionCreateResult;
use Prooph\EventStore\Internal\Data\PersistentSubscriptionDeleteResult;
use Prooph\EventStore\Internal\Data\PersistentSubscriptionUpdateResult;

/** @internal */
interface EventStoreSubscriptionConnection extends EventStoreConnection
{
    public function createPersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): PersistentSubscriptionCreateResult;

    public function updatePersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): PersistentSubscriptionUpdateResult;

    public function deletePersistentSubscription(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): PersistentSubscriptionDeleteResult;

    /**
     * @param string $stream
     * @param string $groupName
     * @param callable(EventStorePersistentSubscription $subscription, RecordedEvent $event, int $retryCount): Promise $eventAppeared
     * @param null|callable(EventStorePersistentSubscription $subscription, SubscriptionDropReason $reason, \Throwable $exception): void $subscriptionDropped
     * @param int $bufferSize
     * @param bool $autoAck
     * @param UserCredentials|null $userCredentials
     * @return EventStorePersistentSubscription
     */
    public function connectToPersistentSubscription(
        string $stream,
        string $groupName,
        callable $eventAppeared,
        callable $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        UserCredentials $userCredentials = null
    ): EventStorePersistentSubscription;
}
