<?php

declare(strict_types=1);

namespace ProophTest\HttpEventStore;

use Prooph\EventStore\EventStoreSyncConnection;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\HttpEventStoreSyncConnection;
use ProophTest\EventStore\EventStoreConnectionTest;

class HttpEventStoreConnectionTest extends EventStoreConnectionTest
{
    protected function getEventStoreConnection(): EventStoreSyncConnection
    {
        return new HttpEventStoreSyncConnection(
            new \Http\Client\Socket\Client(new \Http\Message\MessageFactory\DiactorosMessageFactory()),
            new \Http\Message\MessageFactory\DiactorosMessageFactory(),
            new \Http\Message\UriFactory\DiactorosUriFactory(),
            new \Prooph\HttpEventStore\ConnectionSettings(
                new \Prooph\EventStore\IpEndPoint(\getenv('HTTP_HOST'), (int) \getenv('HTTP_PORT')),
                false,
                new UserCredentials(\getenv('HTTP_USERNAME'), \getenv('HTTP_PASSWORD'))
            )
        );
    }

    protected function cleanEventStore(): void
    {
    }

    protected function getStream(string $name): array
    {
        return [];
    }
}
