<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;

/** @internal */
class DeleteStreamOperation
{
    public function __invoke(PDO $connection, string $stream): void
    {
        $statement = $connection->prepare('DELETE FROM events WHERE streamId = ?');
        $statement->execute([$stream]);
    }
}
