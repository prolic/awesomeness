<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\Task as BaseTask;

/** @internal  */
class EventReadResultTask extends BaseTask
{
    public function result(): EventReadResult
    {
        $callback = $this->callback;
        $response = $this->promise->wait();

        return $callback($response);
    }
}
