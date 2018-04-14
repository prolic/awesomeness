<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore;

use PDO;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\Common\SystemStreams;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventReadStatus;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\EventStoreTransaction;
use Prooph\EventStore\EventStoreTransactionConnection;
use Prooph\EventStore\Exception\ConnectionException;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Internal\Consts;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\StreamMetadataResult;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\WriteResult;
use Prooph\PdoEventStore\ClientOperations\AcquireStreamLockOperation;
use Prooph\PdoEventStore\ClientOperations\AppendToStreamOperation;
use Prooph\PdoEventStore\ClientOperations\CommitTransactionOperation;
use Prooph\PdoEventStore\ClientOperations\DeleteStreamOperation;
use Prooph\PdoEventStore\ClientOperations\ReadEventOperation;
use Prooph\PdoEventStore\ClientOperations\ReadStreamEventsBackwardOperation;
use Prooph\PdoEventStore\ClientOperations\ReadStreamEventsForwardOperation;
use Prooph\PdoEventStore\ClientOperations\ReleaseStreamLockOperation;
use Prooph\PdoEventStore\ClientOperations\StartTransactionOperation;
use Prooph\PdoEventStore\ClientOperations\TransactionalWriteOperation;
use Prooph\PdoEventStore\Internal\LockData;

final class PdoEventStoreConnection implements EventStoreConnection, EventStoreTransactionConnection
{
    /** @var PDO */
    private $connection;

    /** @var LockData[] */
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
    ): void {
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
    ): WriteResult {
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
            $userCredentials,
            true
        );
    }

    public function readEvent(
        string $stream,
        int $eventNumber,
        UserCredentials $userCredentials = null
    ): EventReadResult {
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
    ): StreamEventsSlice {
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
    ): StreamEventsSlice {
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
    ): WriteResult {
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
            $userCredentials,
            true
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

    public function startTransaction(
        string $stream,
        int $expectedVersion,
        UserCredentials $userCredentials = null
    ): EventStoreTransaction {
        if (empty($stream)) {
            throw new \InvalidArgumentException('Stream cannot be empty');
        }

        if (isset($this->locks[$stream])) {
            throw new ConnectionException('Lock on stream ' . $stream . ' is already acquired');
        }

        if ($this->connection->inTransaction()) {
            throw new ConnectionException('PDO connection is already in transaction');
        }

        try {
            /* @var LockData $lockData */
            $lockData = (new StartTransactionOperation())(
                $this->connection,
                $stream,
                $expectedVersion,
                $userCredentials
            );
        } catch (\Exception $e) {
            (new ReleaseStreamLockOperation())($this->connection, $stream);
            unset($this->locks[$stream]);

            throw $e;
        }

        $this->locks[$stream] = $lockData;

        return new EventStoreTransaction(
            $lockData->transactionId(),
            $userCredentials,
            $this
        );
    }

    public function continueTransaction(
        int $transactionId,
        UserCredentials $userCredentials = null
    ): EventStoreTransaction {
        $found = false;

        foreach ($this->locks as $lock) {
            if ($lock->transactionId() === $transactionId) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            throw new ConnectionException('No lock with id ' . $transactionId . ' found');
        }

        (new AcquireStreamLockOperation())($this->connection, $lock->stream());

        $this->locks[$lock->stream()] = new LockData(
            $lock->stream(),
            $transactionId,
            $lock->expectedVersion(),
            $lock->lockCounter() + 1
        );

        return new EventStoreTransaction($transactionId, $userCredentials, $this);
    }

    public function transactionalWrite(
        EventStoreTransaction $transaction,
        array $events,
        UserCredentials $userCredentials = null
    ): void {
        if (empty($events)) {
            throw new \InvalidArgumentException('Empty stream given');
        }

        $found = false;

        foreach ($this->locks as $stream => $data) {
            if ($data['id'] === $transaction->transactionId()) {
                $expectedVersion = $data['expectedVersion'];
                $found = true;
                break;
            }
        }

        if (false === $found) {
            throw new ConnectionException(
                'No lock for transaction with id ' . $transaction->transactionId() . ' found'
            );
        }

        (new TransactionalWriteOperation())(
            $this->connection,
            $stream,
            $expectedVersion,
            $events,
            $userCredentials
        );

        $this->locks[$stream]['expectedVersion'] += count($events);
    }

    public function commitTransaction(
        EventStoreTransaction $transaction,
        UserCredentials $userCredentials = null
    ): WriteResult {
        $found = false;

        foreach ($this->locks as $stream => $lockData) {
            if ($lockData->transactionId() === $transaction->transactionId()) {
                $found = true;
                break;
            }
        }

        if (false === $found) {
            throw new ConnectionException(
                'No lock for transaction with id ' . $transaction->transactionId() . ' found'
            );
        }

        $writeResult = (new CommitTransactionOperation())($this->connection, $stream);

        if ($lockData->lockCounter() > 1) {
            $releaseLockOperation = new ReleaseStreamLockOperation();

            for ($i = 2; $i <= $lockData->lockCounter(), $i++) {
                $releaseLockOperation($this->connection, $stream);
            }
        }

        unset($this->locks[$stream]);

        return $writeResult;
    }
}
