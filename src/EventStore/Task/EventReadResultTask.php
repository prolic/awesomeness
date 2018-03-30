<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\Task as BaseTask;

class EventReadResultTask extends BaseTask
{
    public function result(): EventReadResult
    {
        $this->promise->wait(false);

        return $this->result;
    }
}
