<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore;

use Prooph\EventStore\AllEventsSlice;
use Prooph\EventStore\DeleteResult;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\Position;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\StreamMetadataResult;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\WriteResult;

final class PdoEventStoreConnection implements EventStoreConnection
{
    public function connect(): void
    {
        // TODO: Implement connect() method.
    }

    public function close(): void
    {
        // TODO: Implement close() method.
    }

    public function deleteStream(
        string $stream,
        bool $hardDelete,
        UserCredentials $userCredentials = null
    ): DeleteResult
    {
        // TODO: Implement deleteStream() method.
    }

    public function appendToStream(
        string $stream,
        int $expectedVersion,
        array $events,
        UserCredentials $userCredentials = null
    ): WriteResult
    {
        // TODO: Implement appendToStream() method.
    }

    public function readEvent(
        string $stream,
        int $eventNumber,
        UserCredentials $userCredentials = null
    ): EventReadResult
    {
        // TODO: Implement readEvent() method.
    }

    public function readStreamEventsForward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice
    {
        // TODO: Implement readStreamEventsForward() method.
    }

    public function readStreamEventsBackward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice
    {
        // TODO: Implement readStreamEventsBackward() method.
    }

    public function readAllEventsForward(
        Position $position,
        int $maxCount,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): AllEventsSlice
    {
        // TODO: Implement readAllEventsForward() method.
    }

    public function readAllEventsBackward(
        Position $position,
        int $maxCount,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): AllEventsSlice
    {
        // TODO: Implement readAllEventsBackward() method.
    }

    public function setStreamMetadata(
        string $stream,
        int $expectedMetastreamVersion,
        StreamMetadata $metadata,
        UserCredentials $userCredentials = null
    ): WriteResult
    {
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
}
