<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\StreamMetadataResult;
use Prooph\EventStore\Task as BaseTask;

class StreamMetadataResultTask extends BaseTask
{
    public function result(): StreamMetadataResult
    {
        return $this->promise->wait();
    }
}
