<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

/** @internal */
class Consts
{
    public const DefaultMaxQueueSize = 5000;
    public const DefaultMaxConcurrentItems = 5000;
    public const DefaultMaxOperationRetries = 10;
    public const DefaultMaxReconnections = 10;
    public const DefaultRequireMaster = true;
    public const DefaultReconnectionDelay = 100; // milliseconds
    public const DefaultOperationTimeout = 7; // seconds
    public const DefaultOperationTimeoutCheckPeriod = 1; // seconds
    public const TimerPeriod = 200; // milliseconds
    public const MaxReadSize = 4096;
    public const DefaultMaxClusterDiscoverAttempts = 10;
    public const DefaultClusterManagerExternalHttpPort = 30778;
    public const CatchUpDefaultReadBatchSize = 500;
    public const CatchUpDefaultMaxPushQueueSize = 10000;
}
