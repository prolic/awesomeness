<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Internal;
use Prooph\EventStore\Projections\ProjectionMode;
use Prooph\EventStore\Projections\ProjectionState;

/** @internal */
class StatisticsRecorder
{
    /** @var array[] */
    private $data = [];

    public function record(
        int $second,
        ProjectionState $state,
        string $stateReason,
        bool $enabled,
        string $name,
        string $id,
        ProjectionMode $mode,
        int $eventsPerSecond,
        int $bufferedEvents,
        int $eventsProcessed,
        int $readsInProgress,
        int $writesInProgress,
        int $writeQueue,
        string $checkpointStatus,
        array $position,
        array $lastCheckpoint
    ): void {
        if (isset($this->data[$second])) {
            $this->data[$second]['status'] = $state->name();
            $this->data[$second]['stateReason'] = $stateReason;
            $this->data[$second]['enabled'] = $enabled;
            $this->data[$second]['name'] = $name;
            $this->data[$second]['id'] = $id;
            $this->data[$second]['mode'] = $mode->name();
            $this->data[$second]['bufferedEvents'] = $bufferedEvents;
            $this->data[$second]['eventsPerSecond'] += $eventsPerSecond;
            $this->data[$second]['eventsProcessed'] = $eventsProcessed;
            $this->data[$second]['readsInProgress'] = $readsInProgress;
            $this->data[$second]['writesInProgress'] = $writesInProgress;
            $this->data[$second]['writeQueue'] = $writeQueue;
            $this->data[$second]['checkpointStatus'] = $checkpointStatus;
            $this->data[$second]['position'] = $position;
            $this->data[$second]['lastCheckpoint'] = $lastCheckpoint;
        } else {
            $this->data[$second] = [
                'status' => $state->name(),
                'stateReason' => $stateReason,
                'enabled' => $enabled,
                'name' => $name,
                'id' => $id,
                'mode' => $mode->name(),
                'bufferedEvents' => $bufferedEvents,
                'eventsPerSecond' => $eventsPerSecond,
                'eventsProcessed' => $eventsProcessed,
                'readsInProgress' => $readsInProgress,
                'writesInProgress' => $writesInProgress,
                'writeQueue' => $writeQueue,
                'checkpointStatus' => $checkpointStatus,
                'position' => $position,
                'lastCheckpoint' => $lastCheckpoint,
            ];

            if (count($this->data) > 2) {
                reset($this->data);
                unset($this->data[key($this->data)]);
            }
        }
    }

    public function get(): array
    {
        reset($this->data);
        return $data = current($this->data);
    }
}
