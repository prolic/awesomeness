<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\Exception\RuntimeException;

/** @internal */
class ReleaseStreamLockOperation
{
    public function __invoke(PDO $connection, string $stream, int $count = 1): void
    {
        switch ($connection->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'mysql':
                $sql = 'SELECT RELEASE_LOCK(?) as stream_lock;';
                $statement = $connection->prepare(\str_repeat($sql, $count));
                $statement->execute([$stream]);
                $statement->setFetchMode(PDO::FETCH_OBJ);
                $lock = $statement->fetch()->stream_lock;

                if (! $lock) {
                    throw new RuntimeException('Could not release lock for stream ' . $stream);
                }

                break;
            case 'pgsql':
                $sql = 'SELECT PG_ADVISORY_UNLOCK(HASHTEXT(?)) as stream_lock;';
                $statement = $connection->prepare(\str_repeat($sql, $count));
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
