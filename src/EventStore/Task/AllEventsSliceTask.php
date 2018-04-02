<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\AllEventsSlice;
use Prooph\EventStore\Task as BaseTask;

/** @internal  */
class AllEventsSliceTask extends BaseTask
{
    public function result(): AllEventsSlice
    {
        $callback = $this->callback;
        $response = $this->promise->wait();

        return $callback($response);
    }
}
