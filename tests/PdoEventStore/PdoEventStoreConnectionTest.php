<?php

declare(strict_types=1);

namespace ProophTest\PdoEventStore;

use Prooph\EventStore\EventStoreConnection;
use ProophTest\EventStore\EventStoreConnectionTest;

class PdoEventStoreConnectionTest extends EventStoreConnectionTest
{
    protected function getEventStoreConnection(): EventStoreConnection
    {
        return new \Prooph\PdoEventStore\PdoEventStoreConnection(
            new \Prooph\PdoEventStore\PostgresConnectionSettings(
                new \Prooph\EventStore\IpEndPoint(getenv('PG_HOST'), (int) getenv('PG_PORT')),
                getenv('PG_DBNAME'),
                new \Prooph\EventStore\UserCredentials(getenv('PG_USERNAME'), getenv('PG_PASSWORD')),
                null,
                false
            )
        );
    }
}
