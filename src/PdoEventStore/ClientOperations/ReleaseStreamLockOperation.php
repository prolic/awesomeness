<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\Exception\RuntimeException;

/** @internal */
class ReleaseStreamLockOperation
{
    public function __invoke(PDO $connection, string $stream): void
    {
        switch ($connection->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'mysql':
                $statement = $connection->prepare('SELECT RELEASE_LOCK(?) as stream_lock;');
                $statement->execute([$stream]);
                $statement->setFetchMode(PDO::FETCH_OBJ);
                $lock = $statement->fetch()->stream_lock;

                if (! $lock) {
                    throw new RuntimeException('Could not release lock for stream ' . $stream);
                }

                break;
            case 'pgsql':
                $statement = $connection->prepare('SELECT PG_ADVISORY_UNLOCK(HASHTEXT(?)) as stream_lock;');
                $statement->execute([$stream]);
                $statement->setFetchMode(PDO::FETCH_OBJ);
                $lock = $statement->fetch()->stream_lock;

                if (! $lock) {
                    throw new RuntimeException('Could not release lock for stream ' . $stream);
                }

                break;
            default:
                throw new RuntimeException('Invalid PDO driver used');
        }
    }
}
