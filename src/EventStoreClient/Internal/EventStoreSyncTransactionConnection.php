<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStoreClient\UserCredentials;
use Prooph\EventStoreClient\WriteResult;
use Prooph\EventStoreClient\EventStoreSyncTransaction;

/** @internal */
interface EventStoreSyncTransactionConnection
{
    public function startTransaction(
        string $stream,
        int $expectedVersion,
        UserCredentials $userCredentials = null
    ): EventStoreSyncTransaction;

    public function continueTransaction(
        int $transactionId,
        UserCredentials $userCredentials = null
    ): EventStoreSyncTransaction;

    public function transactionalWrite(
        EventStoreSyncTransaction $transaction,
        array $events,
        UserCredentials $userCredentials = null
    ): void;

    public function commitTransaction(
        EventStoreSyncTransaction $transaction,
        UserCredentials $userCredentials = null
    ): WriteResult;
}
