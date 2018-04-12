<?php

declare(strict_types=1);

namespace Prooph\EventStore;

/** @internal */
interface EventStoreAsyncTransactionConnection
{
    public function transactionalWriteAsync(
        EventStoreTransaction $transaction,
        array $events,
        ?UserCredentials $userCredentials
    ): Task;

    public function commitTransactionAsync(
        EventStoreTransaction $transaction,
        ?UserCredentials $userCredentials
    ): Task\WriteResultTask;
}
