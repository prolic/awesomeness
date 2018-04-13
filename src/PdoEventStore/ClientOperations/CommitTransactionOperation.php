<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\Exception\ConnectionException;
use Prooph\EventStore\WriteResult;

/** @internal */
class CommitTransactionOperation
{
    public function __invoke(PDO $connection, string $stream): WriteResult
    {
        if (! $connection->inTransaction()) {
            (new ReleaseStreamLockOperation())($connection, $stream);

            throw new ConnectionException('PDO connection is not in transaction');
        }

        $connection->commit();

        (new ReleaseStreamLockOperation())($connection, $stream);

        return new WriteResult();
    }
}
