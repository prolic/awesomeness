<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Prooph\EventStore\EventStoreAsyncConnection;

class EventStoreNodeConnection
{
    public static function create(string $connectionString, string $connectionName = null): EventStoreAsyncConnection
    {
    }
}
