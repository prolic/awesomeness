<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\ConditionalWriteResult;
use Prooph\EventStore\Task as BaseTask;

class ConditionalWriteResultTask extends BaseTask
{
    public function result(): ConditionalWriteResult
    {
        return $this->promise->wait();
    }
}
