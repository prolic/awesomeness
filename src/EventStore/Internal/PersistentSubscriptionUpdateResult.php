<?php

declare(strict_types=1);

namespace Prooph\EventStore\Internal;

/** @internal */
class PersistentSubscriptionUpdateResult
{
    /** @var string */
    private $correlationId;
    /** @var string */
    private $reason;
    /** @var PersistentSubscriptionUpdateStatus */
    private $status;

    public function __construct(string $correlationId, string $reason, PersistentSubscriptionUpdateStatus $status)
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

    public function status(): PersistentSubscriptionUpdateStatus
    {
        return $this->status;
    }
}
