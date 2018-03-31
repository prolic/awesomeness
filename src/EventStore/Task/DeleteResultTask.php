<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\DeleteResult;
use Prooph\EventStore\Task as BaseTask;

/** @internal  */
class DeleteResultTask extends BaseTask
{
    public function result(): DeleteResult
    {
        $callback = $this->callback;
        $response = $this->promise->wait();

        return $callback($response);
    }
}
