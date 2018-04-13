<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\Exception\RuntimeException;

/** @internal */
class AcquireStreamLockOperation
{
    public function __invoke(PDO $connection, string $stream): void
    {
        switch ($connection->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'mysql':
                $statement = $connection->prepare('SELECT GET_LOCK(?, ?) as streamLock;');
                $statement->execute([$stream, -1]);
                $statement->setFetchMode(PDO::FETCH_OBJ);
                $lock = $statement->fetch()->streamLock;

                if (! $lock) {
                    throw new RuntimeException('Could not acquire lock for stream ' . $stream);
                }

                break;
            case 'pgsql':
                $statement = $connection->prepare('SELECT PG_ADVISORY_LOCK(HASHTEXT(?)) as streamLock;');
                $statement->execute([$stream]);
                $statement->setFetchMode(PDO::FETCH_OBJ);
                $lock = $statement->fetch()->streamLock;

                if (! $lock) {
                    throw new RuntimeException('Could not acquire lock for stream ' . $stream);
                }

                break;
            default:
                throw new RuntimeException('Invalid PDO driver used');
        }
    }
}
