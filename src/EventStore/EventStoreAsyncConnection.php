<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Amp\Promise;
use Prooph\EventStore\Data\CatchUpSubscriptionSettings;
use Prooph\EventStore\Data\EventData;
use Prooph\EventStore\Data\PersistentSubscriptionSettings;
use Prooph\EventStore\Data\Position;
use Prooph\EventStore\Data\StreamMetadata;
use Prooph\EventStore\Data\SystemSettings;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Internal\Event\ListenerHandler;

interface EventStoreAsyncConnection
{
    public function connectionName(): string;

    public function connectAsync(): Promise;

    public function close(): void;

    /** @return Promise<DeleteResult> */
    public function deleteStreamAsync(
        string $stream,
        int $expectedVersion,
        bool $hardDelete,
        UserCredentials $userCredentials = null
    ): Promise;

    /**
     * @param string $stream
     * @param int $expectedVersion
     * @param null|UserCredentials $userCredentials
     * @param EventData[] $events
     * @return Promise<WriteResult>
     */
    public function appendToStreamAsync(
        string $stream,
        int $expectedVersion,
        array $events,
        UserCredentials $userCredentials = null
    ): Promise;

    /** @return Promise<EventReadResult> */
    public function readEventAsync(
        string $stream,
        int $eventNumber,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): Promise;

    /** @return Promise<StreamEventsSlice> */
    public function readStreamEventsForwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): Promise;

    /** @return Promise<StreamEventsSlice> */
    public function readStreamEventsBackwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): Promise;

    /** @return Promise<AllEventsSlice> */
    public function readAllEventsForward(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): Promise;

    /** @return Promise<AllEventsSlice> */
    public function readAllEventsBackward(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): Promise;

    /** @return Promise<WriteResult> */
    public function setStreamMetadataAsync(
        string $stream,
        int $expectedMetaStreamVersion,
        ?StreamMetadata $metadata,
        UserCredentials $userCredentials = null
    ): Promise;

    /** @return Promise<StreamMetadataResult> */
    public function getStreamMetadataAsync(string $stream, UserCredentials $userCredentials = null): Promise;

    /** @return Promise<WriteResult> */
    public function setSystemSettingsAsync(SystemSettings $settings, UserCredentials $userCredentials = null): Promise;

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
     * @param callable(EventStoreSubscription $subscription, ResolvedEvent $event): Promise $eventAppeared
     * @param null|callable(EventStoreSubscription $subscription, SubscriptionDropReason $reason, Exception $exception): void $subscriptionDropped
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
     * @param callable(EventStoreCatchUpSubscription $subscription, ResolvedEvent $event): Promise $eventAppeared
     * @param null|callable(EventStoreCatchUpSubscription $subscription): void $liveProcessingStarted
     * @param null|callable(EventStoreCatchUpSubscription $subscription, SubscriptionDropReason $reason, Exception $exception): void $subscriptionDropped
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
     * @param callable(EventStoreCatchUpSubscription $subscription, ResolvedEvent $event): Promise $eventAppeared
     * @param null|callable(EventStoreCatchUpSubscription $subscription, SubscriptionDropReason $reason, Exception $exception): void $subscriptionDropped
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
     * @param callable(EventStoreCatchUpSubscription $subscription, ResolvedEvent $event): Promise $eventAppeared
     * @param null|callable(EventStoreCatchUpSubscription $subscription): void $liveProcessingStarted
     * @param null|callable(EventStoreCatchUpSubscription $subscription, SubscriptionDropReason $reason, Exception $exception): void $subscriptionDropped
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

    /**
     * @param string $stream
     * @param string $groupName
     * @param callable(EventStorePersistentSubscription $subscription, RecordedEvent $event, int $retryCount): Promise $eventAppeared
     * @param null|callable(EventStorePersistentSubscription $subscription, SubscriptionDropReason $reason, \Throwable $exception): void $subscriptionDropped
     * @param int $bufferSize
     * @param bool $autoAck
     * @param UserCredentials|null $userCredentials
     * @return Promise<AbstractEventStorePersistentSubscription>
     */
    public function connectToPersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        callable $eventAppeared,
        ?callable $subscriptionDropped,
        int $bufferSize = 10,
        bool $autoAck = true,
        UserCredentials $userCredentials = null
    ): Promise;

    public function onConnected(callable $handler): ListenerHandler;

    public function onDisconnected(callable $handler): ListenerHandler;

    public function onReconnecting(callable $handler): ListenerHandler;

    public function onClosed(callable $handler): ListenerHandler;

    public function onErrorOccurred(callable $handler): ListenerHandler;

    public function onAuthenticationFailed(callable $handler): ListenerHandler;

    public function detach(ListenerHandler $handler): void;
}
