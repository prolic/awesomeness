<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\UserCredentials;

/** @internal */
class AcquireStreamLockOperation
{
    public function __invoke(PDO $connection, string $stream, ?UserCredentials $userCredentials): void
    {
        switch ($connection->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'mysql':
                $statement = $connection->prepare('SELECT GET_LOCK(?, ?) as stream_lock;');
                $statement->execute([$stream, -1]);
                $statement->setFetchMode(PDO::FETCH_OBJ);
                $lock = $statement->fetch()->stream_lock;

                if (! $lock) {
                    throw new RuntimeException('Could not acquire lock for stream ' . $stream);
                }

                break;
            case 'pgsql':
                $statement = $connection->prepare('SELECT PG_ADVISORY_LOCK(HASHTEXT(?)) as stream_lock;');
                $statement->execute([$stream]);
                $statement->setFetchMode(PDO::FETCH_OBJ);
                $statement->fetch();

                break;
            default:
                throw new RuntimeException('Invalid PDO driver used');
        }
    }
}
