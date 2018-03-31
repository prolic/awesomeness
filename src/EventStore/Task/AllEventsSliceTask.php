<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\AllEventsSlice;
use Prooph\EventStore\Task as BaseTask;

class AllEventsSliceTask extends BaseTask
{
    public function result(): AllEventsSlice
    {
        return $this->promise->wait();
    }
}
