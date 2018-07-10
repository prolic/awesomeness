<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

use Prooph\EventStore\NodeEndPoints;
use Prooph\EventStoreClient\Internal\Message;

/** @internal */
class EstablishTcpConnectionMessage implements Message
{
    /** @var NodeEndPoints */
    private $nodeEndPoints;

    public function __construct(NodeEndPoints $nodeEndPoints)
    {
        $this->nodeEndPoints = $nodeEndPoints;
    }

    public function nodeEndPoints(): NodeEndPoints
    {
        return $this->nodeEndPoints;
    }
}
