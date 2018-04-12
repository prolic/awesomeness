<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;

/** @internal */
class DeleteStreamOperation
{
    public function __invoke(PDO $connection, string $stream, bool $hardDelete): void
    {
        if ($hardDelete) {
            $statement = $connection->prepare('UPDATE streams SET markDeleted = ?, delete = ? WHERE streamName = ?');
            $statement->execute([false, true, $stream]);

            $statement = $connection->prepare('DELETE FROM events WHERE streamId = ?');
            $statement->execute([$stream]);
        } else {
            $statement = $connection->prepare('UPDATE streams SET markDeleted = ? WHERE streamName = ?');
            $statement->execute([true, $stream]);
        }
    }
}
