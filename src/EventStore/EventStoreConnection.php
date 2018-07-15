<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Prooph\EventStore\Data\EventData;
use Prooph\EventStore\Data\EventReadResult;
use Prooph\EventStore\Data\Position;
use Prooph\EventStore\Data\StreamEventsSlice;
use Prooph\EventStore\Data\StreamMetadata;
use Prooph\EventStore\Data\StreamMetadataResult;
use Prooph\EventStore\Data\SystemSettings;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Data\WriteResult;
use Prooph\EventStore\Internal\Event\ListenerHandler;
use Prooph\EventStoreClient\ClusterSettings;
use Prooph\EventStoreClient\ConnectionSettings;

interface EventStoreConnection
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

    public function whenConnected(callable $handler): ListenerHandler;

    public function whenDisconnected(callable $handler): ListenerHandler;

    public function whenReconnecting(callable $handler): ListenerHandler;

    public function whenClosed(callable $handler): ListenerHandler;

    public function whenErrorOccurred(callable $handler): ListenerHandler;

    public function whenAuthenticationFailed(callable $handler): ListenerHandler;

    public function detach(ListenerHandler $handler): void;
}
