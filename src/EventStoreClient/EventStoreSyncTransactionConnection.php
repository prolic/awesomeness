<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Prooph\EventStoreClient\Data\UserCredentials;
use Prooph\EventStoreClient\Data\WriteResult;

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
