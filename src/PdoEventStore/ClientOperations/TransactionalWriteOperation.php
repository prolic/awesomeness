<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\Exception\ConnectionException;
use Prooph\EventStore\UserCredentials;

/** @internal */
class TransactionalWriteOperation
{
    public function __invoke(
        PDO $connection,
        string $stream,
        int $expectedVersion,
        array $events,
        ?UserCredentials $userCredentials
    ): void {
        if (! $connection->inTransaction()) {
            (new ReleaseStreamLockOperation())($connection, $stream);

            throw new ConnectionException('PDO connection is not in transaction');
        }

        (new AppendToStreamOperation())(
            $connection,
            $stream,
            $expectedVersion,
            $events,
            $userCredentials,
            false
        );
    }
}
