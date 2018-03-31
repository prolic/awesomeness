<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\EventStoreTransaction;
use Prooph\EventStore\Task as BaseTask;

/** @internal  */
class EventStoreTransactionTask extends BaseTask
{
    public function result(): EventStoreTransaction
    {
        return $this->promise->wait();
    }
}
