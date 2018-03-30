<?php

declare(strict_types=1);

namespace Prooph\EventStore;

class GossipSeed
{
    private $endPoint;
    private $hostHeader;

    public function __construct(IpEndPoint $endPoint, string $hostHeader)
    {
        $this->endPoint = $endPoint;
        $this->hostHeader = $hostHeader;
    }

    public function endPoint(): IpEndPoint
    {
        return $this->endPoint;
    }

    public function hostHeader(): string
    {
        return $this->hostHeader;
    }
}
