<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\StreamMetadataResult;
use Prooph\EventStore\Task as BaseTask;

/** @internal  */
class StreamMetadataResultTask extends BaseTask
{
    public function result(): StreamMetadataResult
    {
        $callback = $this->callback;
        $response = $this->promise->wait();

        return $callback($response);
    }
}
