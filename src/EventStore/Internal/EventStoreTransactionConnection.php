<?php

declare(strict_types=1);

namespace Prooph\EventStore\Internal;

use Prooph\EventStore\EventStoreTransaction;
use Prooph\EventStore\Task;
use Prooph\EventStore\UserCredentials;

/** @internal */
interface EventStoreTransactionConnection
{
    public function transactionalWriteAsync(
        EventStoreTransaction $transaction,
        iterable $events,
        ?UserCredentials $userCredentials
    ): Task;

    public function commitTransactionAsync(
        EventStoreTransaction $transaction,
        ?UserCredentials $userCredentials
    ): Task\WriteResultTask;
}
