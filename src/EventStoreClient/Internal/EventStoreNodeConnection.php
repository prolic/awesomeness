<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\Deferred;
use Amp\Promise;
use Prooph\EventStore\Data\EventReadResult;
use Prooph\EventStore\Data\PersistentSubscriptionSettings;
use Prooph\EventStore\Data\Position;
use Prooph\EventStore\Data\StreamEventsSlice;
use Prooph\EventStore\Data\StreamMetadata;
use Prooph\EventStore\Data\StreamMetadataResult;
use Prooph\EventStore\Data\SystemSettings;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Data\WriteResult;
use Prooph\EventStore\EventStoreConnection as Connection;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\EventStoreTransaction;
use Prooph\EventStore\EventStoreTransactionConnection as TransactionConnection;
use Prooph\EventStore\Internal\Data\PersistentSubscriptionCreateResult;
use Prooph\EventStore\Internal\Data\PersistentSubscriptionDeleteResult;
use Prooph\EventStore\Internal\Data\PersistentSubscriptionUpdateResult;
use Prooph\EventStore\Internal\Event\ListenerHandler;
use Prooph\EventStoreClient\ClusterSettings;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Internal\ClientOperations\CommitTransactionOperation;
use Prooph\EventStoreClient\Internal\ClientOperations\TransactionalWriteOperation;
use Prooph\PdoEventStore\ClientOperations\StartTransactionOperation;

/** @internal */
final class EventStoreNodeConnection implements
    Connection,
    TransactionConnection
{
    /** @var EventStoreAsyncNodeConnection */
    private $asyncConnection;

    public function __construct(EventStoreAsyncNodeConnection $asyncConnection)
    {
        $this->asyncConnection = $asyncConnection;
    }

    public function connectionName(): string
    {
        return $this->asyncConnection->connectionName();
    }

    public function connectionSettings(): ConnectionSettings
    {
        return $this->asyncConnection->connectionSettings();
    }

    public function clusterSettings(): ?ClusterSettings
    {
        return $this->asyncConnection->clusterSettings();
    }

    /** @throws \Throwable */
    public function connect(): void
    {
        Promise\wait($this->asyncConnection->connectAsync());
    }

    public function close(): void
    {
        $this->asyncConnection->close();
    }

    /** @throws \Throwable */
    public function deleteStream(
        string $stream,
        int $expectedVersion,
        bool $hardDelete,
        UserCredentials $userCredentials = null
    ): void {
        Promise\wait($this->asyncConnection->deleteStreamAsync($stream, $expectedVersion, $hardDelete, $userCredentials));
    }

    /** @throws \Throwable */
    public function appendToStream(
        string $stream,
        int $expectedVersion,
        array $events,
        UserCredentials $userCredentials = null
    ): WriteResult {
        return Promise\wait($this->asyncConnection->appendToStreamAsync(
            $stream,
            $expectedVersion,
            $events,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function readEvent(
        string $stream,
        int $eventNumber,
        bool $resolveLinkTo = true,
        UserCredentials $userCredentials = null
    ): EventReadResult {
        return Promise\wait($this->asyncConnection->readEventAsync(
            $stream,
            $eventNumber,
            $resolveLinkTo,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function readStreamEventsForward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        return Promise\wait($this->asyncConnection->readStreamEventsForwardAsync(
            $stream,
            $start,
            $count,
            $resolveLinkTos,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function readStreamEventsBackward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        return Promise\wait($this->asyncConnection->readStreamEventsBackwardAsync(
            $stream,
            $start,
            $count,
            $resolveLinkTos,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function readAllEventsForward(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        return Promise\wait($this->asyncConnection->readAllEventsForward(
            $position,
            $count,
            $resolveLinkTos,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function readAllEventsBackward(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        return Promise\wait($this->asyncConnection->readAllEventsBackward(
            $position,
            $count,
            $resolveLinkTos,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function setStreamMetadata(
        string $stream,
        int $expectedMetaStreamVersion,
        StreamMetadata $metadata,
        UserCredentials $userCredentials = null
    ): WriteResult {
        return Promise\wait($this->asyncConnection->setStreamMetadataAsync(
            $stream,
            $expectedMetaStreamVersion,
            $metadata,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function getStreamMetadata(string $stream, UserCredentials $userCredentials = null): StreamMetadataResult
    {
        return Promise\wait($this->asyncConnection->getStreamMetadataAsync($stream, $userCredentials));
    }

    /** @throws \Throwable */
    public function setSystemSettings(SystemSettings $settings, UserCredentials $userCredentials = null): WriteResult
    {
        return Promise\wait($this->asyncConnection->setSystemSettingsAsync($settings, $userCredentials));
    }

    /** @throws \Throwable */
    public function createPersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): PersistentSubscriptionCreateResult {
        return Promise\wait($this->asyncConnection->createPersistentSubscriptionAsync($stream, $groupName, $settings, $userCredentials));
    }

    /** @throws \Throwable */
    public function updatePersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): PersistentSubscriptionUpdateResult {
        return Promise\wait($this->asyncConnection->updatePersistentSubscriptionAsync(
            $stream,
            $groupName,
            $settings,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function deletePersistentSubscription(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): PersistentSubscriptionDeleteResult {
        return Promise\wait($this->asyncConnection->deletePersistentSubscriptionAsync($stream, $groupName, $userCredentials));
    }

    /** @throws \Throwable */
    public function connectToPersistentSubscription(
        string $stream,
        string $groupName,
        callable $eventAppeared,
        callable $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        UserCredentials $userCredentials = null
    ): EventStorePersistentSubscription {
        return $this->asyncConnection->connectToPersistentSubscription(
            $stream,
            $groupName,
            $eventAppeared,
            $subscriptionDropped,
            $bufferSize,
            $autoAck,
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

        $deferred = new Deferred();

        $reflectionMethod = new \ReflectionMethod(\get_class($this->asyncConnection), 'enqueueOperation');
        $reflectionMethod->setAccessible(true);

        $reflectionMethod->invoke($this->asyncConnection, new StartTransactionOperation(
            $deferred,
            $this->asyncConnection->connectionSettings()->requireMaster(),
            $stream,
            $expectedVersion,
            $this,
            $userCredentials
        ));

        return Promise\wait($deferred->promise());
    }

    /** @throws \Throwable */
    public function continueTransaction(
        int $transactionId,
        UserCredentials $userCredentials = null
    ): EventStoreTransaction {
        if ($transactionId < 0) {
            throw new InvalidArgumentException('Invalid transaction id');
        }

        return new EventStoreTransaction($transactionId, $userCredentials, $this);
    }

    public function transactionalWrite(
        EventStoreTransaction $transaction,
        array $events,
        UserCredentials $userCredentials = null
    ): void {
        if (empty($events)) {
            throw new InvalidArgumentException('No events given');
        }

        $deferred = new Deferred();

        $reflectionMethod = new \ReflectionMethod(\get_class($this->asyncConnection), 'enqueueOperation');
        $reflectionMethod->setAccessible(true);

        $reflectionMethod->invoke($this->asyncConnection, new TransactionalWriteOperation(
            $deferred,
            $this->asyncConnection->connectionSettings()->requireMaster(),
            $transaction->transactionId(),
            $events,
            $userCredentials
        ));

        return Promise\wait($deferred->promise());
    }

    public function commitTransaction(
        EventStoreTransaction $transaction,
        UserCredentials $userCredentials = null
    ): WriteResult {
        $deferred = new Deferred();

        $reflectionMethod = new \ReflectionMethod(\get_class($this->asyncConnection), 'enqueueOperation');
        $reflectionMethod->setAccessible(true);

        $reflectionMethod->invoke($this->asyncConnection, new CommitTransactionOperation(
            $deferred,
            $this->asyncConnection->connectionSettings()->requireMaster(),
            $transaction->transactionId(),
            $userCredentials
        ));

        return Promise\wait($deferred->promise());
    }

    public function onConnected(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->onConnected($handler);
    }

    public function onDisconnected(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->onDisconnected($handler);
    }

    public function onReconnecting(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->onReconnecting($handler);
    }

    public function onClosed(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->onClosed($handler);
    }

    public function onErrorOccurred(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->onErrorOccurred($handler);
    }

    public function onAuthenticationFailed(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->onAuthenticationFailed($handler);
    }

    public function detach(ListenerHandler $handler): void
    {
        $this->asyncConnection->detach($handler);
    }
}
