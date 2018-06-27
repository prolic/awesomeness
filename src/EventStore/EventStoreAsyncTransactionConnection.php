<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Amp\Promise;

/** @internal */
interface EventStoreAsyncTransactionConnection
{
    public function startTransactionAsync(
        string $stream,
        int $expectedVersion,
        UserCredentials $userCredentials = null
    ): EventStoreAsyncTransaction;

    public function transactionalWriteAsync(
        EventStoreAsyncTransaction $transaction,
        array $events,
        ?UserCredentials $userCredentials
    ): Promise;

    /** @return Promise<WriteResult> */
    public function commitTransactionAsync(
        EventStoreAsyncTransaction $transaction,
        ?UserCredentials $userCredentials
    ): Promise;
}
