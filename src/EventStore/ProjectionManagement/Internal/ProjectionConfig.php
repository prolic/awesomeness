<?php

declare(strict_types=1);

namespace Prooph\EventStore\ProjectionManagement\Internal;

use Prooph\EventStore\Internal\Principal;

/** @internal  */
final class ProjectionConfig
{
    /** @var Principal */
    private $runAs;
    /** @var bool */
    private $stopOnEof;
    /** @var bool */
    private $emitEnabled;
    /** @var bool */
    private $trackEmittedStreams;
    /** @var int */
    private $checkpointAfterMs;
    /** @var int */
    private $checkpointHandledThreshold;
    /** @var int */
    private $checkpointUnhandledBytesThreshold;
    /** @var int */
    private $pendingEventsThreshold;
    /** @var int */
    private $maxWriteBatchLength;
    /** @var int|null */
    private $maxAllowedWritesInFlight;

    public function __construct(
        Principal $runAs,
        bool $stopOnEof,
        bool $emitEnabled = false,
        bool $trackEmittedStreams,
        int $checkpointAfterMs = 0,
        int $checkpointHandledThreshold = 4000,
        int $checkpointUnhandledBytesThreshold = 10000000,
        int $pendingEventsThreshold = 5000,
        int $maxWriteBatchLength = 500,
        int $maxAllowedWritesInFlight = null
    ) {
        $this->runAs = $runAs;
        $this->stopOnEof = $stopOnEof;
        $this->emitEnabled = $emitEnabled;
        $this->trackEmittedStreams = $trackEmittedStreams;
        $this->checkpointAfterMs = $checkpointAfterMs;
        $this->checkpointHandledThreshold = $checkpointHandledThreshold;
        $this->checkpointUnhandledBytesThreshold = $checkpointUnhandledBytesThreshold;
        $this->pendingEventsThreshold = $pendingEventsThreshold;
        $this->maxWriteBatchLength = $maxWriteBatchLength;
        $this->maxAllowedWritesInFlight = $maxAllowedWritesInFlight;
    }

    public function runAs(): Principal
    {
        return $this->runAs;
    }

    public function stopOnEof(): bool
    {
        return $this->stopOnEof;
    }

    public function emitEnabled(): bool
    {
        return $this->emitEnabled;
    }

    public function trackEmittedStreams(): bool
    {
        return $this->trackEmittedStreams;
    }

    public function checkpointAfterMs(): int
    {
        return $this->checkpointAfterMs;
    }

    public function checkpointHandledThreshold(): int
    {
        return $this->checkpointHandledThreshold;
    }

    public function checkpointUnhandledBytesThreshold(): int
    {
        return $this->checkpointUnhandledBytesThreshold;
    }

    public function pendingEventsThreshold(): int
    {
        return $this->pendingEventsThreshold;
    }

    public function maxWriteBatchLength(): int
    {
        return $this->maxWriteBatchLength;
    }

    public function maxAllowedWritesInFlight(): ?int
    {
        return $this->maxAllowedWritesInFlight;
    }

    public function toArray(): array
    {
        return [
            'runAs' => $this->runAs->toArray(),
            'stopOnEof' => $this->stopOnEof,
            'emitEnabled' => $this->emitEnabled,
            'trackEmittedStreams' => $this->trackEmittedStreams,
            'checkpointAfterMs' => $this->checkpointAfterMs,
            'checkpointHandledThreshold' => $this->checkpointHandledThreshold,
            'checkpointUnhandledBytesThreshold' => $this->checkpointUnhandledBytesThreshold,
            'pendingEventsThreshold' => $this->pendingEventsThreshold,
            'maxWriteBatchLength' => $this->maxWriteBatchLength,
            'maxAllowedWritesInFlight' => $this->maxAllowedWritesInFlight,
        ];
    }
}
