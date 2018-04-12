<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore;

use PDO;
use Prooph\EventStore\DetailedSubscriptionInformation;
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
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\StreamMetadataResult;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\WriteResult;
use Prooph\PdoEventStore\ClientOperations\DeleteStreamOperation;
use Prooph\PdoEventStore\ClientOperations\ReadEventOperation;

final class PdoEventStore implements EventStoreSubscriptionConnection, EventStoreTransactionConnection
{
    /** @var PDO */
    private $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function connect(): void
    {
        // do nothing
    }

    public function close(): void
    {
        // do nothing
    }

    public function deleteStream(
        string $stream,
        bool $hardDelete,
        UserCredentials $userCredentials = null
    ): void {
        (new DeleteStreamOperation())($this->connection, $stream);
    }

    public function appendToStream(
        string $stream,
        int $expectedVersion,
        array $events,
        UserCredentials $userCredentials = null
    ): WriteResult {
        // TODO: Implement appendToStream() method.
    }

    public function readEvent(
        string $stream,
        int $eventNumber,
        UserCredentials $userCredentials = null
    ): EventReadResult {
        return (new ReadEventOperation())($this->connection, $stream, $eventNumber);
    }

    public function readStreamEventsForward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        // TODO: Implement readStreamEventsForward() method.
    }

    public function readStreamEventsBackward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        // TODO: Implement readStreamEventsBackward() method.
    }

    public function setStreamMetadata(
        string $stream,
        int $expectedMetastreamVersion,
        StreamMetadata $metadata,
        UserCredentials $userCredentials = null
    ): WriteResult {
        // TODO: Implement setStreamMetadata() method.
    }

    public function getStreamMetadata(string $stream, UserCredentials $userCredentials = null): StreamMetadataResult
    {
        // TODO: Implement getStreamMetadata() method.
    }

    public function setSystemSettings(SystemSettings $settings, UserCredentials $userCredentials = null): WriteResult
    {
        // TODO: Implement setSystemSettings() method.
    }

    public function createPersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): PersistentSubscriptionCreateResult {
        // TODO: Implement createPersistentSubscription() method.
    }

    public function updatePersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): PersistentSubscriptionUpdateResult {
        // TODO: Implement updatePersistentSubscription() method.
    }

    public function deletePersistentSubscription(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): PersistentSubscriptionDeleteResult {
        // TODO: Implement deletePersistentSubscription() method.
    }

    public function connectToPersistentSubscription(
        string $stream,
        string $groupName,
        callable $eventAppeared,
        callable $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        UserCredentials $userCredentials = null
    ): EventStorePersistentSubscription {
        // TODO: Implement connectToPersistentSubscription() method.
    }

    public function replayParked(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): ReplayParkedResult {
        // TODO: Implement replayParked() method.
    }

    public function getInformationForAllSubscriptions(
        UserCredentials $userCredentials = null
    ): array {
        // TODO: Implement getInformationForAllSubscriptions() method.
    }

    public function getInformationForSubscriptionsWithStream(
        string $stream,
        UserCredentials $userCredentials = null
    ): array {
        // TODO: Implement getInformationForSubscriptionsWithStream() method.
    }

    public function getInformationForSubscription(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): DetailedSubscriptionInformation {
        // TODO: Implement getInformationForSubscription() method.
    }

    public function transactionalWrite(
        EventStoreTransaction $transaction,
        array $events,
        ?UserCredentials $userCredentials
    ): void {
        // TODO: Implement transactionalWrite() method.
    }

    public function commitTransaction(
        EventStoreTransaction $transaction,
        ?UserCredentials $userCredentials
    ): WriteResult {
        // TODO: Implement commitTransaction() method.
    }
}
