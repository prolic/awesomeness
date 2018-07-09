<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Event;

use Prooph\EventStore\IpEndPoint;
use Prooph\EventStoreClient\EventStoreAsyncConnection;

class ClientConnectionEventArgs implements EventArgs
{
    /** @var EventStoreAsyncConnection */
    private $connection;
    /** @var IpEndPoint */
    private $remoteEndPoint;

    public function __construct(EventStoreAsyncConnection $connection, IpEndPoint $remoteEndPoint)
    {
        $this->connection = $connection;
        $this->remoteEndPoint = $remoteEndPoint;
    }

    public function connection(): EventStoreAsyncConnection
    {
        return $this->connection;
    }

    public function remoteEndPoint(): IpEndPoint
    {
        return $this->remoteEndPoint;
    }
}
