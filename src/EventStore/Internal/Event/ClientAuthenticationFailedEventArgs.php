<?php

declare(strict_types=1);

namespace Prooph\EventStore\Internal\Event;

use Prooph\EventStoreClient\EventStoreAsyncConnection;

class ClientAuthenticationFailedEventArgs implements EventArgs
{
    /** @var EventStoreAsyncConnection */
    private $connection;
    /** @var string */
    private $reason;

    public function __construct(EventStoreAsyncConnection $connection, string $reason)
    {
        $this->connection = $connection;
        $this->reason = $reason;
    }

    public function connection(): EventStoreAsyncConnection
    {
        return $this->connection;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
