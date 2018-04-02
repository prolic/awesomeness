<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\Task as BaseTask;

/** @internal  */
class StreamEventsSliceTask extends BaseTask
{
    public function result(): StreamEventsSlice
    {
        $callback = $this->callback;
        $response = $this->promise->wait();

        return $callback($response);
    }
}
