<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Prooph\EventStoreClient\Data\EventData;
use Prooph\EventStoreClient\Data\EventReadResult;
use Prooph\EventStoreClient\Data\Position;
use Prooph\EventStoreClient\Data\StreamEventsSlice;
use Prooph\EventStoreClient\Data\StreamMetadata;
use Prooph\EventStoreClient\Data\StreamMetadataResult;
use Prooph\EventStoreClient\Data\SystemSettings;
use Prooph\EventStoreClient\Data\UserCredentials;
use Prooph\EventStoreClient\Data\WriteResult;
use Prooph\EventStoreClient\Internal\Event\ListenerHandler;

interface EventStoreSyncConnection
{
    public function connectionName(): string;

    public function connectionSettings(): ConnectionSettings;

    public function clusterSettings(): ?ClusterSettings;

    public function connect(): void;

    public function close(): void;

    public function deleteStream(
        string $stream,
        int $expectedVersion,
        bool $hardDelete,
        UserCredentials $userCredentials = null
    ): void;

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
    ): WriteResult;

    /**
     * for event number see StreamPosition
     */
    public function readEvent(
        string $stream,
        int $eventNumber,
        bool $resolveLinkTo = true,
        UserCredentials $userCredentials = null
    ): EventReadResult;

    public function readStreamEventsForward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice;

    public function readStreamEventsBackward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice;

    public function readAllEventsForward(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice;

    public function readAllEventsBackward(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice;

    public function setStreamMetadata(
        string $stream,
        int $expectedMetaStreamVersion,
        StreamMetadata $metadata,
        UserCredentials $userCredentials = null
    ): WriteResult;

    public function getStreamMetadata(string $stream, UserCredentials $userCredentials = null): StreamMetadataResult;

    public function setSystemSettings(SystemSettings $settings, UserCredentials $userCredentials = null): WriteResult;

    public function onConnected(callable $handler): ListenerHandler;

    public function onDisconnected(callable $handler): ListenerHandler;

    public function onReconnecting(callable $handler): ListenerHandler;

    public function onClosed(callable $handler): ListenerHandler;

    public function onErrorOccurred(callable $handler): ListenerHandler;

    public function onAuthenticationFailed(callable $handler): ListenerHandler;

    public function detach(ListenerHandler $handler): void;
}
