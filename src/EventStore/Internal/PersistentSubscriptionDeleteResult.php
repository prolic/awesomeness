<?php

declare(strict_types=1);

namespace Prooph\EventStore\Internal;

/** @internal */
class PersistentSubscriptionDeleteResult
{
    /** @var string */
    private $correlationId;
    /** @var string */
    private $reason;
    /** @var PersistentSubscriptionDeleteStatus */
    private $status;

    public function __construct(string $correlationId, string $reason, PersistentSubscriptionDeleteStatus $status)
    {
        $this->correlationId = $correlationId;
        $this->reason = $reason;
        $this->status = $status;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function status(): PersistentSubscriptionDeleteStatus
    {
        return $this->status;
    }
}
