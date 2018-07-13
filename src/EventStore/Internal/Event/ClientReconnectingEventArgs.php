<?php

declare(strict_types=1);

namespace Prooph\EventStore\Internal\Event;

use Prooph\EventStoreClient\EventStoreAsyncConnection;

class ClientReconnectingEventArgs implements EventArgs
{
    /** @var EventStoreAsyncConnection */
    private $connection;

    public function __construct(EventStoreAsyncConnection $connection)
    {
        $this->connection = $connection;
    }

    public function connection(): EventStoreAsyncConnection
    {
        return $this->connection;
    }
}
