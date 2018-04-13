<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore;

use PDO;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\Common\SystemStreams;
use Prooph\EventStore\DetailedSubscriptionInformation;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventReadStatus;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\EventStoreSubscriptionConnection;
use Prooph\EventStore\EventStoreTransaction;
use Prooph\EventStore\EventStoreTransactionConnection;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Internal\Consts;
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
use Prooph\PdoEventStore\ClientOperations\AppendToStreamOperation;
use Prooph\PdoEventStore\ClientOperations\DeleteStreamOperation;
use Prooph\PdoEventStore\ClientOperations\ReadEventOperation;
use Prooph\PdoEventStore\ClientOperations\ReadStreamEventsBackwardOperation;
use Prooph\PdoEventStore\ClientOperations\ReadStreamEventsForwardOperation;

final class PdoEventStoreConnection implements EventStoreSubscriptionConnection, EventStoreTransactionConnection
{
    /** @var PDO */
    private $connection;

    /** @var array */
    private $locks = [];

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
    ): void
    {
        if (empty($stream)) {
            throw new \InvalidArgumentException('Stream cannot be empty');
        }

        (new DeleteStreamOperation())($this->connection, $stream, $hardDelete);
    }

    /**
     * @param string $stream
     * @param int $expectedVersion
     * @param null|UserCredentials $userCredentials
     * @param EventData[] $events
     * @return WriteResult
     */
    public function appendToStream(
        string $stream,
        int $expectedVersion,
        array $events,
        UserCredentials $userCredentials = null
    ): WriteResult
    {
        if (empty($stream)) {
            throw new \InvalidArgumentException('Stream cannot be empty');
        }

        if (empty($events)) {
            throw new \InvalidArgumentException('Empty stream given');
        }

        if (isset($this->locks[$stream])) {
            throw new RuntimeException('Lock on stream ' . $stream . ' is already acquired');
        }

        return (new AppendToStreamOperation())(
            $stream,
            $expectedVersion,
            $events,
            $userCredentials
        );
    }

    public function readEvent(
        string $stream,
        int $eventNumber,
        UserCredentials $userCredentials = null
    ): EventReadResult
    {
        if (empty($stream)) {
            throw new \InvalidArgumentException('Stream cannot be empty');
        }

        if ($eventNumber < -1) {
            throw new \InvalidArgumentException('EventNumber cannot be smaller then -1');
        }

        return (new ReadEventOperation())($this->connection, $stream, $eventNumber);
    }

    public function readStreamEventsForward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice
    {
        if (empty($stream)) {
            throw new \InvalidArgumentException('Stream cannot be empty');
        }

        if ($start < 0) {
            throw new \InvalidArgumentException('Start cannot be negative');
        }

        if ($count < 0) {
            throw new \InvalidArgumentException('Count cannot be negative');
        }

        if ($count > Consts::MaxReadSize) {
            throw new \InvalidArgumentException(
                'Count should be less than ' . Consts::MaxReadSize . '. For larger reads you should page.'
            );
        }

        return (new ReadStreamEventsForwardOperation())($this->connection, $stream, $start, $count);
    }

    public function readStreamEventsBackward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice
    {
        if (empty($stream)) {
            throw new \InvalidArgumentException('Stream cannot be empty');
        }

        if ($start < 0) {
            throw new \InvalidArgumentException('Start cannot be negative');
        }

        if ($count < 0) {
            throw new \InvalidArgumentException('Count cannot be negative');
        }

        if ($count > Consts::MaxReadSize) {
            throw new \InvalidArgumentException(
                'Count should be less than ' . Consts::MaxReadSize . '. For larger reads you should page.'
            );
        }

        return (new ReadStreamEventsBackwardOperation())($this->connection, $stream, $start, $count);
    }

    public function setStreamMetadata(
        string $stream,
        int $expectedMetaStreamVersion,
        StreamMetadata $metadata,
        UserCredentials $userCredentials = null
    ): WriteResult
    {
        if (empty($stream)) {
            throw new \InvalidArgumentException('Stream cannot be empty');
        }

        if (SystemStreams::isMetastream($stream)) {
            throw new \InvalidArgumentException(sprintf(
                'Setting metadata for metastream \'%s\' is not supported.',
                $stream
            ));
        }

        $metaEvent = new EventData(
            EventId::generate(),
            SystemEventTypes::StreamMetadata,
            true,
            json_encode($metadata->toArray()),
            ''
        );

        return (new AppendToStreamOperation())(
            $this->connection,
            SystemStreams::metastreamOf($stream),
            $expectedMetaStreamVersion,
            [$metaEvent],
            $userCredentials
        );
    }

    public function getStreamMetadata(string $stream, UserCredentials $userCredentials = null): StreamMetadataResult
    {
        if (empty($stream)) {
            throw new \InvalidArgumentException('Stream cannot be empty');
        }

        $eventReadResult = $this->readEvent(
            SystemStreams::metastreamOf($stream),
            -1,
            $userCredentials
        );

        switch ($eventReadResult->status()->value()) {
            case EventReadStatus::Success:
                $event = $eventReadResult->event();

                if (null === $event) {
                    throw new \UnexpectedValueException('Event is null while operation result is Success');
                }

                return new StreamMetadataResult(
                    $stream,
                    false,
                    $event->eventNumber(),
                    $event->data()
                );
            case EventReadStatus::NotFound:
            case EventReadStatus::NoStream:
                return new StreamMetadataResult($stream, false, -1, '');
            case EventReadStatus::StreamDeleted:
                return new StreamMetadataResult($stream, true, PHP_INT_MAX, '');
            default:
                throw new \OutOfRangeException('Unexpected ReadEventResult: ' . $eventReadResult->status()->value());
        }
    }

    public function setSystemSettings(SystemSettings $settings, UserCredentials $userCredentials = null): WriteResult
    {
        return $this->appendToStream(
            SystemStreams::SettingsStream,
            ExpectedVersion::Any,
            [
                new EventData(
                    EventId::generate(),
                    SystemEventTypes::Settings,
                    true,
                    json_encode($settings->toArray()),
                    ''
                ),
            ],
            $userCredentials
        );
    }

    public function createPersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): PersistentSubscriptionCreateResult
    {
        // TODO: Implement createPersistentSubscription() method.
    }

    public function updatePersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): PersistentSubscriptionUpdateResult
    {
        // TODO: Implement updatePersistentSubscription() method.
    }

    public function deletePersistentSubscription(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): PersistentSubscriptionDeleteResult
    {
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
    ): EventStorePersistentSubscription
    {
        // TODO: Implement connectToPersistentSubscription() method.
    }

    public function replayParked(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): ReplayParkedResult
    {
        // TODO: Implement replayParked() method.
    }

    public function getInformationForAllSubscriptions(
        UserCredentials $userCredentials = null
    ): array
    {
        // TODO: Implement getInformationForAllSubscriptions() method.
    }

    public function getInformationForSubscriptionsWithStream(
        string $stream,
        UserCredentials $userCredentials = null
    ): array
    {
        // TODO: Implement getInformationForSubscriptionsWithStream() method.
    }

    public function getInformationForSubscription(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): DetailedSubscriptionInformation
    {
        // TODO: Implement getInformationForSubscription() method.
    }

    public function startTransaction(
        string $stream,
        int $expectedVersion,
        UserCredentials $userCredentials = null
    ): EventStoreTransaction
    {
        // TODO: Implement startTransaction() method.
    }

    public function transactionalWrite(
        EventStoreTransaction $transaction,
        array $events,
        UserCredentials $userCredentials = null
    ): void
    {
        // TODO: Implement transactionalWrite() method.
    }

    public function commitTransaction(
        EventStoreTransaction $transaction,
        UserCredentials $userCredentials = null
    ): WriteResult
    {
        // TODO: Implement commitTransaction() method.
    }
}
