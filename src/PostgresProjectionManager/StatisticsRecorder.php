<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager;

/** @internal */
class StatisticsRecorder
{
    /** @var array[] */
    private $data = [];

    public function record(
        float $time,
        int $events
    ): void {
        $timeString = (string) $time;
        if (! isset($this->data[$timeString])) {
            $this->data[$timeString] = $events;
        } else {
            $this->data[$timeString] += $events;
        }

        // clean up records older then 2 seconds
        foreach (\array_keys($this->data) as $key) {
            if ($time - 2 > (float) $key) {
                unset($this->data[$key]);
            }
        }
    }

    public function eventsPerSecond(): float
    {
        $now = \microtime(true);

        $totalEvents = 0;
        $beginTime = \PHP_INT_MAX;

        foreach ($this->data as $timeString => $events) {
            $time = (float) $timeString;

            if ($time + 3 < $now) {
                continue;
            }

            if ($time < $beginTime) {
                $beginTime = $time;
            }

            $totalEvents += $events;
        }

        if (0 === $totalEvents) {
            return 0;
        }

        $time = $now - $beginTime;

        return \floor(($totalEvents / $time) * 10000) / 10000;
    }
}
