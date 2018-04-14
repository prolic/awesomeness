<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\UserCredentials;

/** @internal */
class DeleteStreamOperation
{
    public function __invoke(PDO $connection, string $stream, bool $hardDelete, ?UserCredentials $userCredentials): void
    {
        if ($hardDelete) {
            $statement = $connection->prepare('UPDATE streams SET mark_deleted = ?, delete = ? WHERE stream_name = ?');
            $statement->execute([false, true, $stream]);

            $statement = $connection->prepare('DELETE FROM events WHERE stream_id = ?');
            $statement->execute([$stream]);
        } else {
            $statement = $connection->prepare('UPDATE streams SET mark_deleted = ? WHERE stream_name = ?');
            $statement->execute([true, $stream]);
        }
    }
}
