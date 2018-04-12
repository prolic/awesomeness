<?php

declare(strict_types=1);

namespace Prooph\PostgresEventStore\Task;

use pq\Connection;
use Prooph\EventStore\Task;

class ConnectTask extends Task
{
    /** @var Connection */
    private $connection;
    /** @var bool */
    private $connected;
    /** @var \RuntimeException|null */
    private $error;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->connected = false;
    }

    public function result()
    {
        $this->wait();

        if (! $this->connected) {
            throw $this->error;
        }
    }

    public function continueWith(callable $callback, string $task = __CLASS__): Task
    {
        return parent::continueWith($callback, $task);
    }

    public function wait(): void
    {
        $w = [$this->connection->socket];
        $r = $e = null;

        if (stream_select($r, $w, $e, null)) {
            // loop until the connection is established
            while (true) {
                switch ($this->connection->poll()) {
                    case Connection::POLLING_READING:
                        // we should wait for the stream to be read-ready
                        $r = [$this->connection->socket];
                        stream_select($r, $w, $e, NULL);
                        break;
                    case Connection::POLLING_WRITING:
                        // we should wait for the stream to be write-ready
                        $w = [$this->connection->socket];
                        $r = $e = null;
                        stream_select($r, $w, $e, null);
                        break;
                    case Connection::POLLING_FAILED:
                        $this->error = new \RuntimeException(sprintf(
                            "Connection failed: %s\n",
                            $this->connection->errorMessage
                        ));
                        break 2;
                    case Connection::POLLING_OK:
                        $this->connected = true;
                        break 2;
                }
            }
        }
    }

    public function isPending(): bool
    {
        return ! $this->connected && ! $this->error;
    }

    public function isCompleted(): bool
    {
        return $this->connected;
    }

    public function isFaulted(): bool
    {
        return (bool) $this->error;
    }
}
