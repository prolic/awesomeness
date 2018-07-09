<?php

declare(strict_types=1);

namespace Prooph\EventStore\Internal\SystemData;

use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\IpEndPoint;

// @todo: no usage yet, suitable for connection handler logic

/** @internal */
class InspectionResult
{
    /** @var InspectionDecision */
    private $inspectionDecision;
    /** @var string */
    private $description;
    /** @var IpEndPoint|null */
    private $tcpEndPoint;
    /** @var IpEndPoint|null */
    private $secureTcpEndPoint;

    public function __construct(
        InspectionDecision $decision,
        string $description,
        IpEndPoint $tcpEndPoint = null,
        IpEndPoint $secureTcpEndPoint = null)
    {
        if ($decision->equals(InspectionDecision::reconnect())) {
            if (null === $tcpEndPoint) {
                throw new InvalidArgumentException('TcpEndPoint is null for reconnect');
            }
        } elseif (null !== $tcpEndPoint) {
            throw new InvalidArgumentException('TcpEndPoint is not null for decision ' . $decision->name());
        }

        $this->inspectionDecision = $decision;
        $this->description = $description;
        $this->tcpEndPoint = $tcpEndPoint;
        $this->secureTcpEndPoint = $secureTcpEndPoint;
    }

    public function inspectionDecision(): InspectionDecision
    {
        return $this->inspectionDecision;
    }

    public function description(): string
    {
        return $this->description;
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
