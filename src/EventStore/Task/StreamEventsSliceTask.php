<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\Task as BaseTask;

class StreamEventsSliceTask extends BaseTask
{
    public function result(): StreamEventsSlice
    {
        $this->promise->wait(false);

        return $this->result;
    }
}
