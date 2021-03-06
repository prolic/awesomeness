<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Prooph\EventStore\Exception\InvalidArgumentException;

class NodeEndPoints
{
    /** @var IpEndPoint|null */
    private $tcpEndPoint;
    /** @var IpEndPoint|null */
    private $secureTcpEndPoint;

    public function __construct(?IpEndPoint $tcpEndPoint, IpEndPoint $secureTcpEndPoint = null)
    {
        if (($tcpEndPoint && $secureTcpEndPoint) === null) {
            throw new InvalidArgumentException('Both endpoints are null');
        }

        $this->tcpEndPoint = $tcpEndPoint;
        $this->secureTcpEndPoint = $secureTcpEndPoint;
    }

    public function tcpEndPoint(): ?IpEndPoint
    {
        return $this->tcpEndPoint;
    }

    public function secureTcpEndPoint(): ?IpEndPoint
    {
        return $this->secureTcpEndPoint;
    }
}
