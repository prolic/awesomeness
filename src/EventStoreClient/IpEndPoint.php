<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

class IpEndPoint
{
    private $host;
    private $port;

    public function __construct(string $host, int $port)
    {
        $this->host = $host;
        $this->port = $port;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function port(): int
    {
        return $this->port;
    }
}
