<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\StreamMetadataResult;
use Prooph\EventStore\Task as BaseTask;

class StreamMetadataResultTask extends BaseTask
{
    public function result(bool $wait = false): ?StreamMetadataResult
    {
        if ($wait) {
            $this->promise->wait(false);
        }

        return $this->result;
    }
}
