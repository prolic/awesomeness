<?php

declare(strict_types=1);

namespace Prooph\EventStore\Internal\Event;

use Prooph\EventStoreClient\Internal\EventStoreAsyncNodeConnection;

class ClientAuthenticationFailedEventArgs implements EventArgs
{
    /** @var EventStoreAsyncNodeConnection */
    private $connection;
    /** @var string */
    private $reason;

    public function __construct(EventStoreAsyncNodeConnection $connection, string $reason)
    {
        $this->connection = $connection;
        $this->reason = $reason;
    }

    public function connection(): EventStoreAsyncNodeConnection
    {
        return $this->connection;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
