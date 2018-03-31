<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Prooph\EventStore\Task\AllEventsSliceTask;
use Prooph\EventStore\Task\ConditionalWriteResultTask;
use Prooph\EventStore\Task\DeleteResultTask;
use Prooph\EventStore\Task\EventReadResultTask;
use Prooph\EventStore\Task\StreamEventsSliceTask;
use Prooph\EventStore\Task\StreamMetadataResultTask;
use Prooph\EventStore\Task\WriteResultTask;

interface EventStoreConnection
{
    public function connectionName(): string;

    public function connectAsync(): Task;

    public function close(): void;

    public function deleteStreamAsync(
        string $stream,
        bool $hardDelete,
        UserCredentials $userCredentials = null
    ): DeleteResultTask;

    /**
     * @param string $stream
     * @param int $expectedVersion
     * @param null|UserCredentials $userCredentials
     * @param EventData[] $events
     * @return WriteResultTask
     */
    public function appendToStreamAsync(
        string $stream,
        int $expectedVersion,
        ?UserCredentials $userCredentials,
        iterable $events
    ): WriteResultTask;

    public function conditionalAppendToStreamAsync(
        string $stream,
        int $expectedVersion,
        ?UserCredentials $userCredentials,
        iterable $events
    ): ConditionalWriteResultTask;

    public function readEventAsync(
        string $stream,
        int $eventNumber,
        ?UserCredentials $userCredentials
    ): EventReadResultTask;

    public function readStreamEventsForwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ): StreamEventsSliceTask;

    public function readStreamEventsBackwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ): StreamEventsSliceTask;

    public function readAllEventsForwardAsync(
        Position $position,
        int $maxCount,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ): AllEventsSliceTask;

    public function readAllEventsBackwardAsync(
        Position $position,
        int $maxCount,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ): AllEventsSliceTask;

    public function setStreamMetadataAsync(
        string $stream,
        int $expectedMetastreamVersion,
        StreamMetadata $metadata,
        ?UserCredentials $userCredentials
    ): WriteResultTask;

    public function getStreamMetadataAsync(string $stream, ?UserCredentials $userCredentials): StreamMetadataResultTask;

    public function setSystemSettingsAsync(SystemSettings $settings, ?UserCredentials $userCredentials): Task;

    // @todo subscriptions
    // @todo event handlers
}
