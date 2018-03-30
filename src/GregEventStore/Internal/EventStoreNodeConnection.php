<?php

declare(strict_types=1);

namespace Prooph\GregEventStore\Internal;

use Prooph\EventStore\ConnectionSettings;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\EventStoreTransaction;
use Prooph\EventStore\Internal\EventStoreTransactionConnection;
use Prooph\EventStore\Position;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\Task;
use Prooph\EventStore\Task\AllEventsSliceTask;
use Prooph\EventStore\Task\ConditionalWriteResultTask;
use Prooph\EventStore\Task\DeleteResultTask;
use Prooph\EventStore\Task\EventReadResultTask;
use Prooph\EventStore\Task\EventStoreTransactionTask;
use Prooph\EventStore\Task\StreamEventsSliceTask;
use Prooph\EventStore\Task\StreamMetadataResultTask;
use Prooph\EventStore\Task\WriteResultTask;
use Prooph\EventStore\UserCredentials;
use Ramsey\Uuid\Uuid;

class EventStoreNodeConnection implements EventStoreConnection, EventStoreTransactionConnection
{
    /** @var string */
    private $connectionName;
    /** @var ConnectionSettings */
    private $settings;

    public function __construct(ConnectionSettings $settings, ?string $connectionName)
    {
        $this->settings = $settings;
        $this->connectionName = $connectionName ?? sprintf('ES-%s', Uuid::uuid4()->toString());
    }

    public function connectionName(): string
    {
        return $this->connectionName;
    }

    public function connectAsync(): Task
    {
        // TODO: Implement connectAsync() method.
    }

    public function close(): void
    {
        // TODO: Implement close() method.
    }

    public function deleteStreamAsync(
        string $stream,
        int $expectedVersion,
        bool $hardDelete,
        ?UserCredentials $userCredentials
    ): DeleteResultTask {
        // TODO: Implement deleteStreamAsync() method.
    }

    /**
     * @param string $stream
     * @param int $expectedVersion
     * @param null|UserCredentials $userCredentials
     * @param EventData[] $events
     * @return WriteResultTask
     */
    public function appendToStreamAsync(
        string $stream,
        int $expectedVersion,
        ?UserCredentials $userCredentials,
        iterable $events
    ): WriteResultTask {
        // TODO: Implement appendToStreamAsync() method.
    }

    /**
     * @param string $stream
     * @param int $expectedVersion
     * @param null|UserCredentials $userCredentials
     * @param EventData[] $events
     * @return ConditionalWriteResultTask
     */
    public function conditionalAppendToStreamAsync(
        string $stream,
        int $expectedVersion,
        ?UserCredentials $userCredentials,
        iterable $events
    ): ConditionalWriteResultTask {
        // TODO: Implement conditionalAppendToStreamAsync() method.
    }

    public function startTransactionAsync(
        string $stream,
        int $expectedVersion,
        ?UserCredentials $userCredentials
    ): EventStoreTransactionTask {
        // TODO: Implement startTransactionAsync() method.
    }

    public function continueTransaction(int $transactionId, ?UserCredentials $userCredentials): EventStoreTransaction
    {
        // TODO: Implement continueTransaction() method.
    }

    public function readEventAsync(
        string $stream,
        int $eventNumber,
        bool $resultLinkTos,
        ?UserCredentials $userCredentials
    ): EventReadResultTask {
        // TODO: Implement readEventAsync() method.
    }

    public function readStreamEventsForwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ): StreamEventsSliceTask {
        // TODO: Implement readStreamEventsForwardAsync() method.
    }

    public function readStreamEventsBackwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ): StreamEventsSliceTask {
        // TODO: Implement readStreamEventsBackwardAsync() method.
    }

    public function readAllEventsForwardAsync(
        Position $position,
        int $maxCount,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ): AllEventsSliceTask {
        // TODO: Implement readAllEventsForwardAsync() method.
    }

    public function readAllEventsBackwardAsync(
        Position $position,
        int $maxCount,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ): AllEventsSliceTask {
        // TODO: Implement readAllEventsBackwardAsync() method.
    }

    public function setStreamMetadataAsync(
        string $stream,
        int $expectedMetastreamVersion,
        StreamMetadata $metadata,
        ?UserCredentials $userCredentials
    ): WriteResultTask {
        // TODO: Implement setStreamMetadataAsync() method.
    }

    public function getStreamMetadataAsync(string $stream, ?UserCredentials $userCredentials): StreamMetadataResultTask
    {
        // TODO: Implement getStreamMetadataAsync() method.
    }

    public function setSystemSettingsAsync(SystemSettings $settings, ?UserCredentials $userCredentials): Task
    {
        // TODO: Implement setSystemSettingsAsync() method.
    }

    public function settings(): ConnectionSettings
    {
        return $this->settings;
    }

    public function transactionalWriteAsync(
        EventStoreTransaction $transaction,
        iterable $events,
        ?UserCredentials $userCredentials
    ): Task {
        // TODO: Implement transactionalWriteAsync() method.
    }

    public function commitTransactionAsync(
        EventStoreTransaction $transaction,
        ?UserCredentials $userCredentials
    ): Task\WriteResultTask {
        // TODO: Implement commitTransactionAsync() method.
    }
}
