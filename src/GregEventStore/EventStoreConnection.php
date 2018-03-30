<?php

declare(strict_types=1);

namespace Prooph\GregEventStore;

use Prooph\EventStore\ConnectionSettings;
use Prooph\GregEventStore\Internal\EventStoreNodeConnection;
use Psr\Http\Message\UriInterface as Uri;

class EventStoreConnection
{
    public static function create(
        ?ConnectionSettings $connectionSettings,
        Uri $uri,
        ?string $connectionName
    ): \Prooph\EventStore\EventStoreConnection {
        // @todo create cluster settings and pass uri
        $connectionSettings = $connectionSettings ?? ConnectionSettings::defaultSettings();

        return new EventStoreNodeConnection($connectionSettings, $connectionName);
    }
}
