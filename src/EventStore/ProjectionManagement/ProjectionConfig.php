<?php

declare(strict_types=1);

namespace Prooph\EventStore\ProjectionManagement;

final class ProjectionConfig
{
    /**
     * This setting is disabled by default, and is usually set when you
     * create the projection and if you need the projection to emit events
     * @var bool
     */
    private $emitEnabled;
    /**
     * This prevents a new checkpoint from being written within a certain time frame from the previous one.
     * The aim of this option is to keep a projection from writing too many checkpoints too quickly
     * something that can happen in a very busy system.
     * Default is 0
     * @var int
     */
    private $checkpointAfterMs;
    /**
     * This controls the number of events that a projection can handle before attempting to write a checkpoint.
     * The default for this option is 4,000 events.
     * @var int
     */
    private $checkpointHandledThreshold;
    /**
     * This specifies the number of bytes a projection can process before attempting to write a checkpoint.
     * This option defaults to 10mb.
     * @var int
     */
    private $checkpointUnhandledBytesThreshold;
    /**
     * This determines the number of events that can be pending before the projection is paused.
     * The default is 5000.
     * @var int
     */
    private $pendingEventsThreshold;
    /**
     * This determines the maximum number of events the projection can write in a batch at a time.
     * The default for this option is 500
     * @var int
     */
    private $maxWriteBatchLength;
    /**
     * This sets the maximum number of writes to allow for a projection.
     * Because a projection can write to multiple different streams,
     * it is possible for the projection to send off multiple writes at the same time.
     * This option sets the number of concurrent writes that a projection can perform.
     * Default is null (unbound)
     * @var int|null
     */
    private $maxAllowedWritesInFlight;

    public function __construct(
        bool $emitEnabled = false,
        int $checkpointAfterMs = 0,
        int $checkpointHandledThreshold = 4000,
        int $checkpointUnhandledBytesThreshold = 10000000,
        int $pendingEventsThreshold = 5000,
        int $maxWriteBatchLength = 500,
        int $maxAllowedWritesInFlight = null
    ) {
        $this->emitEnabled = $emitEnabled;
        $this->checkpointAfterMs = $checkpointAfterMs;
        $this->checkpointHandledThreshold = $checkpointHandledThreshold;
        $this->checkpointUnhandledBytesThreshold = $checkpointUnhandledBytesThreshold;
        $this->pendingEventsThreshold = $pendingEventsThreshold;
        $this->maxWriteBatchLength = $maxWriteBatchLength;
        $this->maxAllowedWritesInFlight = $maxAllowedWritesInFlight;
    }

    public function emitEnabled(): bool
    {
        return $this->emitEnabled;
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
            'emitEnabled' => $this->emitEnabled,
            'checkpointAfterMs' => $this->checkpointAfterMs,
            'checkpointHandledThreshold' => $this->checkpointHandledThreshold,
            'checkpointUnhandledBytesThreshold' => $this->checkpointUnhandledBytesThreshold,
            'pendingEventsThreshold' => $this->pendingEventsThreshold,
            'maxWriteBatchLength' => $this->maxWriteBatchLength,
            'maxAllowedWritesInFlight' => $this->maxAllowedWritesInFlight,
        ];
    }
}
