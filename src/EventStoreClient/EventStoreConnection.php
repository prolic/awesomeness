<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Amp\Promise;
use Prooph\EventStore\Data\DetailedSubscriptionInformation;
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
use Prooph\EventStore\EventStoreSubscriptionConnection as SubscriptionConnection;
use Prooph\EventStore\EventStoreTransaction;
use Prooph\EventStore\EventStoreTransactionConnection as TransactionConnection;
use Prooph\EventStore\Internal\Data\PersistentSubscriptionCreateResult;
use Prooph\EventStore\Internal\Data\PersistentSubscriptionDeleteResult;
use Prooph\EventStore\Internal\Data\PersistentSubscriptionUpdateResult;
use Prooph\EventStore\Internal\Data\ReplayParkedResult;
use Prooph\EventStoreClient\Event\ListenerHandler;

final class EventStoreConnection implements
    Connection,
    TransactionConnection,
    SubscriptionConnection
{
    /** @var EventStoreAsyncConnection */
    private $asyncConnection;

    public function __construct(EventStoreAsyncConnection $asyncConnection)
    {
        $this->asyncConnection = $asyncConnection;
    }

    public function connectionName(): string
    {
        return $this->asyncConnection->connectionName();
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

    /** @throws \Throwable */
    public function replayParked(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): ReplayParkedResult {
        return Promise\wait($this->asyncConnection->replayParkedAsync($stream, $groupName, $userCredentials));
    }

    /** @throws \Throwable */
    public function getInformationForAllSubscriptions(
        UserCredentials $userCredentials = null
    ): array {
        return Promise\wait($this->asyncConnection->getInformationForAllSubscriptionsAsync($userCredentials));
    }

    /** @throws \Throwable */
    public function getInformationForSubscriptionsWithStream(
        string $stream,
        UserCredentials $userCredentials = null
    ): array {
        return Promise\wait($this->asyncConnection->getInformationForSubscriptionsWithStreamAsync($stream, $userCredentials));
    }

    /** @throws \Throwable */
    public function getInformationForSubscription(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): DetailedSubscriptionInformation {
        return Promise\wait($this->asyncConnection->getInformationForSubscriptionAsync($stream, $groupName, $userCredentials));
    }

    public function startTransaction(
        string $stream,
        int $expectedVersion,
        UserCredentials $userCredentials = null
    ): EventStoreTransaction {
        // @todo fix async transaction
        return Promise\wait($this->asyncConnection->startTransactionAsync($stream, $expectedVersion, $userCredentials));
    }

    /** @throws \Throwable */
    public function continueTransaction(
        int $transactionId,
        UserCredentials $userCredentials = null
    ): EventStoreTransaction {
        return Promise\wait($this->asyncConnection->commitTransactionAsync($transactionId, $userCredentials));
    }

    public function transactionalWrite(
        EventStoreTransaction $transaction,
        array $events,
        UserCredentials $userCredentials = null
    ): void {
        // @todo fix async transaction
        Promise\wait($this->asyncConnection->transactionalWriteAsync($transaction, $events, $userCredentials));
    }

    public function commitTransaction(
        EventStoreTransaction $transaction,
        UserCredentials $userCredentials = null
    ): WriteResult {
        // @todo fix async transaction
        return Promise\wait($this->asyncConnection->commitTransactionAsync($transaction, $userCredentials));
    }

    public function whenConnected(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->whenConnected($handler);
    }

    public function whenDisconnected(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->whenDisconnected($handler);
    }

    public function whenReconnecting(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->whenReconnecting($handler);
    }

    public function whenClosed(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->whenClosed($handler);
    }

    public function whenErrorOccurred(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->whenErrorOccurred($handler);
    }

    public function whenAuthenticationFailed(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->whenAuthenticationFailed($handler);
    }

    public function detach(ListenerHandler $handler): void
    {
        $this->asyncConnection->detach($handler);
    }
}
