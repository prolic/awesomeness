<?php

declare(strict_types=1);

namespace Prooph\EventStore;

/** @internal */
interface EventStoreTransactionConnection
{
    public function startTransactionAsync(
        string $stream,
        int $expectedVersion,
        UserCredentials $userCredentials = null
    );

    /* only available with TCP connection, not available with rest-interface */
    public function continueTransaction(int $transactionId, UserCredentials $userCredentials = null);

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
