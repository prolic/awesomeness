<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Amp\Promise;

interface EventStoreAsyncSubscriptionConnection extends EventStoreAsyncConnection
{
    /** @return Promise<CreatePersistentSubscription> */
    public function createPersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): Promise;

    /** @return Promise<UpdatePersistentSubscription> */
    public function updatePersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): Promise;

    /** @return Promise<DeletePersistentSubscription> */
    public function deletePersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): Promise;

    /**
     * @param string $stream
     * @param string $groupName
     * @param callable(EventStorePersistentSubscription $subscription, RecordedEvent $event, int $retryCount, Task $task) $eventAppeared
     * @param callable(EventStorePersistentSubscription $subscription, SubscriptionDropReason $reason, \Throwable $exception)|null $subscriptionDropped
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

    /** @return Promise<ReplayParkedResult> */
    public function replayParkedAsync(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): Promise;

    /** @return Promise<GetInformationForSubscriptions> */
    public function getInformationForAllSubscriptionsAsync(
        UserCredentials $userCredentials = null
    ): Promise;

    /** @return Promise<GetInformationForSubscriptions> */
    public function getInformationForSubscriptionsWithStreamAsync(
        string $stream,
        UserCredentials $userCredentials = null
    ): Promise;

    /** @return Promise<GetInformationForSubscription> */
    public function getInformationForSubscriptionAsync(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): Promise;
}
