<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Event;

use Prooph\EventStoreClient\EventStoreAsyncConnection;
use Throwable;

class ClientErrorEventArgs implements EventArgs
{
    /** @var EventStoreAsyncConnection */
    private $connection;
    /** @var Throwable */
    private $exception;

    public function __construct(EventStoreAsyncConnection $connection, Throwable $exception)
    {
        $this->connection = $connection;
        $this->exception = $exception;
    }

    public function connection(): EventStoreAsyncConnection
    {
        return $this->connection;
    }

    public function exception(): Throwable
    {
        return $this->exception;
    }
}
