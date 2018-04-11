<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Prooph\EventStore\Task\EventStoreTransactionTask;

/** @internal */
interface EventStoreTransactionConnection
{
    public function startTransactionAsync(
        string $stream,
        int $expectedVersion,
        UserCredentials $userCredentials = null
    ): EventStoreTransactionTask;

    /* only available with TCP connection, not available with rest-interface */
    public function continueTransaction(int $transactionId, UserCredentials $userCredentials = null): EventStoreTransaction;

    public function transactionalWriteAsync(
        EventStoreTransaction $transaction,
        array $events,
        UserCredentials $userCredentials = null
    ): Task;

    public function commitTransactionAsync(
        EventStoreTransaction $transaction,
        UserCredentials $userCredentials = null
    ): Task\WriteResultTask;
}
