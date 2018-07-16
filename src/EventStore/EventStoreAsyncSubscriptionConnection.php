<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Amp\Promise;
use Prooph\EventStore\Data\CatchUpSubscriptionSettings;
use Prooph\EventStore\Data\PersistentSubscriptionSettings;
use Prooph\EventStore\Data\Position;
use Prooph\EventStore\Data\UserCredentials;

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
     * @param bool $resolveLinkTos
     * @param callable(EventStoreSubscription $subscription, ResolvedEvent $event, Promise $promise) $eventAppeared
     * @param callable(EventStoreSubscription $subscription, SubscriptionDropReason $reason, Exception $exception)|null $subscriptionDropped
     * @param UserCredentials|null
     * @return Promise<EventStoreSubscription>
     */
    public function subscribeToStreamAsync(
        string $stream,
        bool $resolveLinkTos,
        callable $eventAppeared,
        callable $subscriptionDropped = null,
        UserCredentials $userCredentials = null
    ): Promise;

    /**
     * @param string $stream
     * @param int|null $lastCheckpoint
     * @param CatchUpSubscriptionSettings $settings
     * @param callable(EventStoreCatchUpSubscription $subscription, ResolvedEvent $event, Promise $promise) $eventAppeared
     * @param callable(EventStoreCatchUpSubscription $subscription)|null $liveProcessingStarted
     * @param callable(EventStoreCatchUpSubscription $subscription, SubscriptionDropReason $reason, Exception $exception)|null $subscriptionDropped
     * @return EventStoreStreamCatchUpSubscription
     */
    public function subscribeToStreamFrom(
        string $stream,
        ?int $lastCheckpoint,
        CatchUpSubscriptionSettings $settings,
        callable $eventAppeared,
        callable $liveProcessingStarted = null,
        callable $subscriptionDropped = null,
        UserCredentials $userCredentials = null
    ): EventStoreStreamCatchUpSubscription;

    /**
     * @param bool $resolveLinkTos
     * @param callable(EventStoreCatchUpSubscription $subscription, ResolvedEvent $event, Promise $promise) $eventAppeared
     * @param callable(EventStoreCatchUpSubscription $subscription, SubscriptionDropReason $reason, Exception $exception)|null $subscriptionDropped
     * @return Promise<EventStoreSubscription>
     */
    public function subscribeToAllAsync(
        bool $resolveLinkTos,
        callable $eventAppeared,
        callable $subscriptionDropped = null,
        UserCredentials $userCredentials = null
    ): Promise;

    /**
     * @param Position|null $lastCheckpoint
     * @param CatchUpSubscriptionSettings $settings
     * @param callable(EventStoreCatchUpSubscription $subscription, ResolvedEvent $event, Promise $promise) $eventAppeared
     * @param callable(EventStoreCatchUpSubscription $subscription)|null $liveProcessingStarted
     * @param callable(EventStoreCatchUpSubscription $subscription, SubscriptionDropReason $reason, Exception $exception)|null $subscriptionDropped
     * @return EventStoreAllCatchUpSubscription
     */
    public function subscribeToAllFrom(
        ?Position $lastCheckpoint,
        CatchUpSubscriptionSettings $settings,
        callable $eventAppeared,
        callable $liveProcessingStarted = null,
        callable $subscriptionDropped = null,
        UserCredentials $userCredentials = null
    ): EventStoreAllCatchUpSubscription;

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

    /**
     * @param string $stream
     * @param string $groupName
     * @param callable(EventStorePersistentSubscription $subscription, RecordedEvent $event, int $retryCount, Task $task) $eventAppeared
     * @param callable(EventStorePersistentSubscription $subscription, SubscriptionDropReason $reason, \Throwable $exception)|null $subscriptionDropped
     * @param int $bufferSize
     * @param bool $autoAck
     * @param UserCredentials|null $userCredentials
     * @return Promise<AbstractEventStorePersistentSubscription>
     */
    public function connectToPersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        callable $eventAppeared,
        callable $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        UserCredentials $userCredentials = null
    ): Promise;
}
