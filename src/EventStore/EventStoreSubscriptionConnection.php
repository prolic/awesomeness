<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Prooph\EventStore\Task\CreatePersistentSubscriptionTask;
use Prooph\EventStore\Task\DeletePersistentSubscriptionTask;
use Prooph\EventStore\Task\GetInformationForSubscriptionsTask;
use Prooph\EventStore\Task\GetInformationForSubscriptionTask;
use Prooph\EventStore\Task\UpdatePersistentSubscriptionTask;

interface EventStoreSubscriptionConnection
{
    public function createPersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): CreatePersistentSubscriptionTask;

    public function updatePersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): UpdatePersistentSubscriptionTask;

    public function deletePersistentSubscription(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): DeletePersistentSubscriptionTask;

    /**
     * @param string $stream
     * @param string $groupName
     * @param callable(EventStorePersistentSubscription $subscription, RecordedEvent $event, int $retryCount, Task $task) $eventAppeared,
     * @param callable(EventStorePersistentSubscription $subscription, SubscriptionDropReason $reason, \Throwable $exception)|null $subscriptionDropped
     * @param int $bufferSize
     * @param bool $autoAck
     * @param UserCredentials|null $userCredentials
     * @return Task
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

    public function ack(string $stream, string $groupName, EventId $eventId): Task;

    /**
     * @param string $stream
     * @param string $groupName
     * @param EventId[] $eventIds
     * @return Task
     */
    public function ackMultiple(string $stream, string $groupName, iterable $eventIds): Task;

    public function nack(string $stream, string $groupName, EventId $eventId): Task;

    /**
     * @param string $stream
     * @param string $groupName
     * @param EventId[] $eventIds
     * @return Task
     */
    public function nackMultiple(string $stream, string $groupName, iterable $eventIds): Task;

    public function replayParked(string $stream, string $groupName): Task;

    public function getInformationForAllSubscriptions(
        UserCredentials $userCredentials = null
    ): GetInformationForSubscriptionsTask;

    public function getInformationForSubscriptionsWithStream(
        string $stream,
        UserCredentials $userCredentials = null
    ): GetInformationForSubscriptionsTask;

    public function getInformationForSubscription(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): GetInformationForSubscriptionTask;
}
