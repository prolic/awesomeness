<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\Task as BaseTask;
use Prooph\EventStore\WriteResult;

/** @internal  */
class WriteResultTask extends BaseTask
{
    public function result(): WriteResult
    {
        $callback = $this->callback;
        $response = $this->promise->wait();

        return $callback($response);
    }
}
