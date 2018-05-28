<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore;

use PDO;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\Common\SystemStreams;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventReadStatus;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\EventStoreTransaction;
use Prooph\EventStore\EventStoreTransactionConnection;
use Prooph\EventStore\Exception\ConnectionException;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\OutOfRangeException;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\UnexpectedValueException;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Internal\Consts;
use Prooph\EventStore\ReadDirection;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\StreamMetadataResult;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\WriteResult;
use Prooph\PdoEventStore\ClientOperations\AcquireStreamLockOperation;
use Prooph\PdoEventStore\ClientOperations\AppendToStreamOperation;
use Prooph\PdoEventStore\ClientOperations\AuthenticateOperation;
use Prooph\PdoEventStore\ClientOperations\DeleteStreamOperation;
use Prooph\PdoEventStore\ClientOperations\LoadStreamOperation;
use Prooph\PdoEventStore\ClientOperations\LoadSystemSettingsOperation;
use Prooph\PdoEventStore\ClientOperations\ReadEventOperation;
use Prooph\PdoEventStore\ClientOperations\ReadStreamEventsBackwardOperation;
use Prooph\PdoEventStore\ClientOperations\ReadStreamEventsForwardOperation;
use Prooph\PdoEventStore\ClientOperations\ReleaseStreamLockOperation;
use Prooph\PdoEventStore\ClientOperations\StartTransactionOperation;
use Prooph\PdoEventStore\Internal\LoadStreamResult;
use Prooph\PdoEventStore\Internal\LockData;
use Prooph\PdoEventStore\Internal\StreamOperation;
use Prooph\PdoEventStore\Internal\TransactionData;

final class PdoEventStoreConnection implements EventStoreConnection, EventStoreTransactionConnection
{
    /** @var ConnectionSettings */
    private $settings;
    /** @var PDO */
    private $connection;
    /** @var LockData[] */
    private $locks = [];
    /** @var array */
    private $transactionData = [];
    /** @var SystemSettings */
    private $systemSettings;
    /** @var array */
    private $userRoles = [];

    public function __construct(ConnectionSettings $settings)
    {
        $this->settings = $settings;
    }

    public function connect(): void
    {
        if (null === $this->connection) {
            $this->connection = new PDO(
                $this->settings->connectionString(),
                $this->settings->pdoUserCredentials()->username(),
                $this->settings->pdoUserCredentials()->password()
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->systemSettings = (new LoadSystemSettingsOperation())($this->connection);

            if ($this->settings->defaultUserCredentials()) {
                $this->userRoles[
                    $this->settings->defaultUserCredentials()->username()
                ] = (new AuthenticateOperation())($this->connection, $this->settings->defaultUserCredentials());
            }
        }
    }

    public function close(): void
    {
        $this->connection = null;
    }

    public function deleteStream(
        string $stream,
        bool $hardDelete,
        UserCredentials $userCredentials = null
    ): void {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $this->checkConnection($userCredentials);

        (new LoadStreamOperation())(
            $this->connection,
            $stream,
            StreamOperation::Delete,
            $this->systemSettings,
            $this->userRoles($userCredentials)
        );

        (new DeleteStreamOperation())(
            $this->connection,
            $stream,
            $hardDelete,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
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
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (empty($events)) {
            throw new InvalidArgumentException('Empty stream given');
        }

        if (isset($this->locks[$stream])) {
            throw new RuntimeException('Lock on stream ' . $stream . ' is already acquired');
        }

        $this->checkConnection($userCredentials);

        /* @var LoadStreamResult $loadStreamResult */
        $loadStreamResult = (new LoadStreamOperation())(
            $this->connection,
            $stream,
            StreamOperation::Delete,
            $this->systemSettings,
            $this->userRoles($userCredentials)
        );

        return (new AppendToStreamOperation())(
            $this->connection,
            $stream,
            $loadStreamResult->found(),
            $expectedVersion,
            $events,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function readEvent(
        string $stream,
        int $eventNumber,
        UserCredentials $userCredentials = null
    ): EventReadResult {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if ($eventNumber < -1) {
            throw new InvalidArgumentException('Event number cannot be smaller then -1');
        }

        $this->checkConnection($userCredentials);

        try {
            /* @var LoadStreamResult $loadStreamResult */
            $loadStreamResult = (new LoadStreamOperation())(
                $this->connection,
                $stream,
                SystemStreams::isMetastream($stream) ? StreamOperation::MetaRead : StreamOperation::Read,
                $this->systemSettings,
                $this->userRoles($userCredentials)
            );
        } catch (StreamDeleted $e) {
            return new EventReadResult(EventReadStatus::streamDeleted(), $stream, $eventNumber, null);
        }

        if (! $loadStreamResult->found()) {
            return new EventReadResult(EventReadStatus::notFound(), $stream, $eventNumber, null);
        }

        return (new ReadEventOperation())(
            $this->connection,
            $stream,
            $eventNumber,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function readStreamEventsForward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if ($start < 0) {
            throw new InvalidArgumentException('Start cannot be negative');
        }

        if ($count < 0) {
            throw new InvalidArgumentException('Count cannot be negative');
        }

        if ($count > Consts::MaxReadSize) {
            throw new InvalidArgumentException(
                'Count should be less than ' . Consts::MaxReadSize . '. For larger reads you should page.'
            );
        }

        $this->checkConnection($userCredentials);

        try {
            /* @var LoadStreamResult $loadStreamResult */
            $loadStreamResult = (new LoadStreamOperation())(
                $this->connection,
                $stream,
                SystemStreams::isMetastream($stream) ? StreamOperation::MetaRead : StreamOperation::Read,
                $this->systemSettings,
                $this->userRoles($userCredentials)
            );
        } catch (StreamDeleted $e) {
            return new StreamEventsSlice(
                SliceReadStatus::streamDeleted(),
                $stream,
                $start,
                ReadDirection::forward(),
                [],
                0,
                0,
                true
            );
        }

        if (! $loadStreamResult->found()) {
            return new StreamEventsSlice(
                SliceReadStatus::streamNotFound(),
                $stream,
                $start,
                ReadDirection::forward(),
                [],
                0,
                0,
                true
            );
        }

        return (new ReadStreamEventsForwardOperation())(
            $this->connection,
            $stream,
            $start,
            $count,
            $resolveLinkTos,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function readStreamEventsBackward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if ($start < 0) {
            throw new InvalidArgumentException('Start cannot be negative');
        }

        if ($count < 0) {
            throw new InvalidArgumentException('Count cannot be negative');
        }

        if ($count > Consts::MaxReadSize) {
            throw new InvalidArgumentException(
                'Count should be less than ' . Consts::MaxReadSize . '. For larger reads you should page.'
            );
        }

        $this->checkConnection($userCredentials);

        try {
            /* @var LoadStreamResult $loadStreamResult */
            $loadStreamResult = (new LoadStreamOperation())(
                $this->connection,
                $stream,
                SystemStreams::isMetastream($stream) ? StreamOperation::MetaRead : StreamOperation::Read,
                $this->systemSettings,
                $this->userRoles($userCredentials)
            );
        } catch (StreamDeleted $e) {
            return new StreamEventsSlice(
                SliceReadStatus::streamDeleted(),
                $stream,
                $start,
                ReadDirection::backward(),
                [],
                0,
                0,
                true
            );
        }

        if (! $loadStreamResult->found()) {
            return new StreamEventsSlice(
                SliceReadStatus::streamNotFound(),
                $stream,
                $start,
                ReadDirection::backward(),
                [],
                0,
                0,
                true
            );
        }

        return (new ReadStreamEventsBackwardOperation())(
            $this->connection,
            $stream,
            $start,
            $count,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function setStreamMetadata(
        string $stream,
        int $expectedMetaStreamVersion,
        StreamMetadata $metadata,
        UserCredentials $userCredentials = null
    ): WriteResult {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (SystemStreams::isMetastream($stream)) {
            throw new InvalidArgumentException(sprintf(
                'Setting metadata for metastream \'%s\' is not supported.',
                $stream
            ));
        }

        $this->checkConnection($userCredentials);

        /* @var LoadStreamResult $loadStreamResult */
        $loadStreamResult = (new LoadStreamOperation())(
            $this->connection,
            SystemStreams::metastreamOf($stream),
            StreamOperation::MetaWrite,
            $this->systemSettings,
            $this->userRoles($userCredentials)
        );

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
            $loadStreamResult->found(),
            $expectedMetaStreamVersion,
            [$metaEvent],
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function getStreamMetadata(string $stream, UserCredentials $userCredentials = null): StreamMetadataResult
    {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $this->checkConnection($userCredentials);

        $eventReadResult = $this->readEvent(
            SystemStreams::metastreamOf($stream),
            -1,
            $userCredentials
        );

        switch ($eventReadResult->status()->value()) {
            case EventReadStatus::Success:
                $event = $eventReadResult->event();

                if (null === $event) {
                    throw new UnexpectedValueException('Event is null while operation result is Success');
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
                throw new OutOfRangeException('Unexpected ReadEventResult: ' . $eventReadResult->status()->value());
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
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (isset($this->locks[$stream])) {
            throw new ConnectionException('Lock on stream ' . $stream . ' is already acquired');
        }

        $this->checkConnection($userCredentials);

        if ($this->connection->inTransaction()) {
            throw new ConnectionException('PDO connection is already in transaction');
        }

        (new LoadStreamOperation())(
            $this->connection,
            $stream,
            SystemStreams::isMetastream($stream) ? StreamOperation::MetaRead : StreamOperation::Read,
            $this->systemSettings,
            $this->userRoles($userCredentials)
        );

        try {
            /* @var LockData $lockData */
            $lockData = (new StartTransactionOperation())(
                $this->connection,
                $stream,
                $expectedVersion,
                $userCredentials ?? $this->settings->defaultUserCredentials()
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
        $this->checkConnection($userCredentials);

        $found = false;

        foreach ($this->locks as $lock) {
            if ($lock->transactionId() === $transactionId) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            throw new ConnectionException(
                'No lock for transaction with id ' . $transactionId . ' found'
            );
        }

        (new AcquireStreamLockOperation())(
            $this->connection,
            $lock->stream(),
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

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
            throw new InvalidArgumentException('Empty stream given');
        }

        $this->checkConnection($userCredentials);

        $found = false;

        foreach ($this->locks as $stream => $lockData) {
            if ($lockData->transactionId() === $transaction->transactionId()) {
                $expectedVersion = $lockData->expectedVersion();
                $found = true;
                break;
            }
        }

        if (false === $found) {
            throw new ConnectionException(
                'No lock for transaction with id ' . $transaction->transactionId() . ' found'
            );
        }

        $this->transactionData[$transaction->transactionId()][] = new TransactionData(
            $events,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        if ($expectedVersion === ExpectedVersion::Any) {
            $newExpectedVersion = ExpectedVersion::Any;
        } elseif ($expectedVersion < 0) {
            $newExpectedVersion = count($events) - 1;
        } else {
            $newExpectedVersion = $expectedVersion + count($events);
        }

        $this->locks[$stream] = new LockData(
            $stream,
            $lockData->transactionId(),
            $newExpectedVersion,
            $lockData->lockCounter()
        );
    }

    public function commitTransaction(
        EventStoreTransaction $transaction,
        UserCredentials $userCredentials = null
    ): WriteResult {
        if (null === $this->connection) {
            throw new ConnectionException('No connection established');
        }

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

        $this->connection->beginTransaction();

        foreach ($this->transactionData[$lockData->transactionId()] as $transactionData) {
            /* @var TransactionData $transactionData */
            (new AppendToStreamOperation())(
                $this->connection,
                $stream,
                $lockData->expectedVersion(),
                $transactionData->events(),
                $transactionData->userCredentials(),
                false
            );
        }

        $this->connection->commit();

        (new ReleaseStreamLockOperation())($this->connection, $stream, $lockData->lockCounter());

        unset($this->locks[$stream], $this->transactionData[$lockData->transactionId()]);

        return new WriteResult();
    }

    public function settings(): ConnectionSettings
    {
        return $this->settings;
    }

    private function checkConnection(?UserCredentials $userCredentials): void
    {
        if (null === $this->connection) {
            throw new ConnectionException('No connection established');
        }

        if ($userCredentials && ! isset($this->userRoles[$userCredentials->username()])) {
            $this->userRoles[$userCredentials->username()] = (new AuthenticateOperation())(
                $this->connection,
                $this->settings->defaultUserCredentials()
            );
        }
    }

    private function userRoles(?UserCredentials $userCredentials): array
    {
        if ($userCredentials) {
            if (! isset($this->userRoles[$userCredentials->username()])) {
                $this->userRoles[$userCredentials->username()] = (new AuthenticateOperation())($this->connection, $userCredentials);
            }

            return $this->userRoles[$userCredentials->username()];
        }

        if ($this->settings->defaultUserCredentials()) {
            return $this->userRoles[$this->settings->defaultUserCredentials()->username()];
        }

        return [SystemRoles::All];
    }
}
