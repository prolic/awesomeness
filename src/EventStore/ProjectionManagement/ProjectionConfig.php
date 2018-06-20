<?php

declare(strict_types=1);

namespace Prooph\EventStore\ProjectionManagement;

class ProjectionConfig
{
    /**
     * This setting is disabled by default, and is usually set when you
     * create the projection and if you need the projection to emit events
     * @var bool
     */
    private $emitEnabled;
    /**
     * Whether or not to enable checkpoints
     * @var bool
     */
    private $checkpointsEnabled;
    /**
     * This setting enables tracking of a projection's emitted streams.
     * It will only have an affect if the projection has EmitEnabled enabled.
     * Tracking emitted streams enables you to delete a projection and all the
     * streams that it has created. You should only use it if you intend to delete
     * a projection and create new ones that project to the same stream.
     * @var bool
     */
    private $trackEmittedStreams;
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
     * @todo currently ignored by postgres projection manager
     * @var int|null
     */
    private $maxAllowedWritesInFlight;

    public function __construct(
        bool $emitEnabled = false,
        bool $checkpointsEnabled = false,
        bool $trackEmittedStreams = false,
        int $checkpointAfterMs = 0,
        int $checkpointHandledThreshold = 4000,
        int $checkpointUnhandledBytesThreshold = 10000000,
        int $pendingEventsThreshold = 5000,
        int $maxWriteBatchLength = 500,
        int $maxAllowedWritesInFlight = null
    ) {
        $this->emitEnabled = $emitEnabled;
        $this->checkpointsEnabled = $checkpointsEnabled;
        $this->trackEmittedStreams = $trackEmittedStreams;
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

    public function checkpointsEnabled(): bool
    {
        return $this->checkpointsEnabled;
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
            'emitEnabled' => $this->emitEnabled,
            'checkpointsEnabled' => $this->checkpointsEnabled,
            'trackEmittedStreams' => $this->trackEmittedStreams,
            'checkpointAfterMs' => $this->checkpointAfterMs,
            'checkpointHandledThreshold' => $this->checkpointHandledThreshold,
            'checkpointUnhandledBytesThreshold' => $this->checkpointUnhandledBytesThreshold,
            'pendingEventsThreshold' => $this->pendingEventsThreshold,
            'maxWriteBatchLength' => $this->maxWriteBatchLength,
            'maxAllowedWritesInFlight' => $this->maxAllowedWritesInFlight,
        ];
    }

    public static function fromArray(array $data): ProjectionConfig
    {
        return new self(
            $data['emitEnabled'] ?? false,
            $data['checkpointsEnabled'] ?? false,
            $data['trackEmittedStreams'] ?? false,
            $data['checkpointAfterMs'] ?? 0,
            $data['checkpointHandledThreshold'] ?? 4000,
            $data['checkpointUnhandledBytesThreshold'] ?? 10000000,
            $data['pendingEventsThreshold'] ?? 5000,
            $data['maxWriteBatchLength'] ?? 500,
            $data['maxAllowedWritesInFlight'] ?? null
        );
    }
}
