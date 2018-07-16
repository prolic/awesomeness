<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Amp\Promise;
use Prooph\EventStore\Data\EventData;
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

    public function onConnected(callable $handler): ListenerHandler;

    public function onDisconnected(callable $handler): ListenerHandler;

    public function onReconnecting(callable $handler): ListenerHandler;

    public function onClosed(callable $handler): ListenerHandler;

    public function onErrorOccurred(callable $handler): ListenerHandler;

    public function onAuthenticationFailed(callable $handler): ListenerHandler;

    public function detach(ListenerHandler $handler): void;
}
