<?php

declare(strict_types=1);

namespace Prooph\GregEventStore;

use Http\Client\HttpAsyncClient;
use Prooph\EventStore\ConnectionSettings;
use Prooph\GregEventStore\Internal\HttpEventStoreNodeConnection;
use Psr\Http\Message\UriInterface as Uri;

class EventStoreHttpConnection
{
    public static function create(
        HttpAsyncClient $asyncClient,
        ?ConnectionSettings $connectionSettings,
        Uri $uri,
        ?string $connectionName
    ): \Prooph\EventStore\EventStoreConnection {
        // @todo create cluster settings and pass uri
        $connectionSettings = $connectionSettings ?? ConnectionSettings::defaultSettings();

        return new HttpEventStoreNodeConnection($asyncClient, $connectionSettings, $connectionName);
    }
}
