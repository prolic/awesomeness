<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\EventStoreTransaction;
use Prooph\EventStore\Task as BaseTask;

class EventStoreTransactionTask extends BaseTask
{
    public function result(): EventStoreTransaction
    {
        $this->promise->wait(false);

        return $this->result;
    }
}