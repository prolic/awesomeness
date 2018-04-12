<?php

declare(strict_types=1);

namespace Prooph\PostgresEventStore;

use Http\Promise\FulfilledPromise;
use pq\Connection;
use Prooph\EventStore\AllEventsSlice;
use Prooph\EventStore\DeleteResult;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\EventStoreSubscriptionConnection;
use Prooph\EventStore\EventStoreTransaction;
use Prooph\EventStore\EventStoreTransactionConnection;
use Prooph\EventStore\Internal\PersistentSubscriptionCreateResult;
use Prooph\EventStore\Internal\PersistentSubscriptionDeleteResult;
use Prooph\EventStore\Internal\PersistentSubscriptionUpdateResult;
use Prooph\EventStore\Internal\ReplayParkedResult;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\Position;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\StreamMetadataResult;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\Task;
use Prooph\EventStore\Task\AllEventsSliceTask;
use Prooph\EventStore\Task\CreatePersistentSubscriptionTask;
use Prooph\EventStore\Task\DeletePersistentSubscriptionTask;
use Prooph\EventStore\Task\DeleteResultTask;
use Prooph\EventStore\Task\EventReadResultTask;
use Prooph\EventStore\Task\EventStoreTransactionTask;
use Prooph\EventStore\Task\GetInformationForSubscriptionsTask;
use Prooph\EventStore\Task\GetInformationForSubscriptionTask;
use Prooph\EventStore\Task\ReplayParkedTask;
use Prooph\EventStore\Task\StreamEventsSliceTask;
use Prooph\EventStore\Task\StreamMetadataResultTask;
use Prooph\EventStore\Task\UpdatePersistentSubscriptionTask;
use Prooph\EventStore\Task\WriteResultTask;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\WriteResult;
use Prooph\PostgresEventStore\Task\ConnectTask;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

final class PostgresEventStore implements EventStoreSubscriptionConnection, EventStoreTransactionConnection
{
    /** @var ConnectionSettings */
    private $settings;
    /** @var ?Connection */
    private $connection;

    public function __construct(ConnectionSettings $settings)
    {
        $this->settings = $settings;
    }

    public function connectAsync(): Task
    {
        if ($this->settings->persistent()) {
            $flags = Connection::ASYNC | Connection::PERSISTENT;
        } else {
            $flags = Connection::ASYNC;
        }
        $this->connection = new Connection($this->settings->connectionString(), $flags);

        return new ConnectTask($this->connection);
    }

    public function close(): void
    {
        $this->connection = null;
    }

    public function deleteStreamAsync(
        string $stream,
        bool $hardDelete,
        UserCredentials $userCredentials = null
    ): DeleteResultTask
    {
        // TODO: Implement deleteStreamAsync() method.
    }

    public function deleteStreamAsync2222(
        string $stream,
        bool $hardDelete,
        UserCredentials $userCredentials = null
    )
    {
        $deferred = new Deferred();
        $promise = $deferred->promise();

        $this->connection->execParamsAsync('DELETE FROM events WHERE streamId = ?', [$stream], null, function ($res) use ($deferred) {
            $deferred->resolve(new DeleteResult());
        });

        return new ConnectTask($this->connection);
    }

    public function appendToStreamAsync(
        string $stream,
        int $expectedVersion,
        array $events,
        UserCredentials $userCredentials = null
    ): WriteResultTask
    {
        // TODO: Implement appendToStreamAsync() method.
    }

    public function readEventAsync(
        string $stream,
        int $eventNumber,
        UserCredentials $userCredentials = null
    ): EventReadResultTask
    {
        // TODO: Implement readEventAsync() method.
    }

    public function readStreamEventsForwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSliceTask
    {
        // TODO: Implement readStreamEventsForwardAsync() method.
    }

    public function readStreamEventsBackwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSliceTask
    {
        // TODO: Implement readStreamEventsBackwardAsync() method.
    }

    public function readAllEventsForwardAsync(
        Position $position,
        int $maxCount,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): AllEventsSliceTask
    {
        // TODO: Implement readAllEventsForwardAsync() method.
    }

    public function readAllEventsBackwardAsync(
        Position $position,
        int $maxCount,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): AllEventsSliceTask
    {
        // TODO: Implement readAllEventsBackwardAsync() method.
    }

    public function setStreamMetadataAsync(
        string $stream,
        int $expectedMetastreamVersion,
        StreamMetadata $metadata,
        UserCredentials $userCredentials = null
    ): WriteResultTask
    {
        // TODO: Implement setStreamMetadataAsync() method.
    }

    public function getStreamMetadataAsync(string $stream, UserCredentials $userCredentials = null): StreamMetadataResultTask
    {
        // TODO: Implement getStreamMetadataAsync() method.
    }

    public function setSystemSettingsAsync(SystemSettings $settings, UserCredentials $userCredentials = null): WriteResultTask
    {
        // TODO: Implement setSystemSettingsAsync() method.
    }

    public function createPersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): CreatePersistentSubscriptionTask
    {
        // TODO: Implement createPersistentSubscriptionAsync() method.
    }

    public function updatePersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): UpdatePersistentSubscriptionTask
    {
        // TODO: Implement updatePersistentSubscriptionAsync() method.
    }

    public function deletePersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): DeletePersistentSubscriptionTask
    {
        // TODO: Implement deletePersistentSubscriptionAsync() method.
    }

    public function connectToPersistentSubscription(
        string $stream,
        string $groupName,
        callable $eventAppeared,
        callable $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        UserCredentials $userCredentials = null
    ): EventStorePersistentSubscription
    {
        // TODO: Implement connectToPersistentSubscription() method.
    }

    public function replayParkedAsync(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): ReplayParkedTask
    {
        // TODO: Implement replayParkedAsync() method.
    }

    public function getInformationForAllSubscriptionsAsync(
        UserCredentials $userCredentials = null
    ): GetInformationForSubscriptionsTask
    {
        // TODO: Implement getInformationForAllSubscriptionsAsync() method.
    }

    public function getInformationForSubscriptionsWithStreamAsync(
        string $stream,
        UserCredentials $userCredentials = null
    ): GetInformationForSubscriptionsTask
    {
        // TODO: Implement getInformationForSubscriptionsWithStreamAsync() method.
    }

    public function getInformationForSubscriptionAsync(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): GetInformationForSubscriptionTask
    {
        // TODO: Implement getInformationForSubscriptionAsync() method.
    }

    public function startTransactionAsync(
        string $stream,
        int $expectedVersion,
        UserCredentials $userCredentials = null
    ): EventStoreTransactionTask
    {
        // TODO: Implement startTransactionAsync() method.
    }

    public function continueTransaction(int $transactionId, UserCredentials $userCredentials = null): EventStoreTransaction
    {
        throw new \BadMethodCallException('This method is not available');
    }

    public function transactionalWriteAsync(
        EventStoreTransaction $transaction,
        array $events,
        UserCredentials $userCredentials = null
    ): Task
    {
        // TODO: Implement transactionalWriteAsync() method.
    }

    public function commitTransactionAsync(
        EventStoreTransaction $transaction,
        UserCredentials $userCredentials = null
    ): Task\WriteResultTask
    {
        // TODO: Implement commitTransactionAsync() method.
    }

}
