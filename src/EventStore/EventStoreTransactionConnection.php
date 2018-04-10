<?php

declare(strict_types=1);

namespace Prooph\EventStore;

/** @internal */
interface EventStoreTransactionConnection
{
    public function transactionalWrite(
        EventStoreTransaction $transaction,
        array $events,
        ?UserCredentials $userCredentials
    ): void;

    public function commitTransaction(
        EventStoreTransaction $transaction,
        ?UserCredentials $userCredentials
    ): WriteResult;
}
