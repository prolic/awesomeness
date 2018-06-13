<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Internal;

/** @internal */
class StatisticsRecorder
{
    /** @var array[] */
    private $data = [];

    public function record(
        int $second,
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
