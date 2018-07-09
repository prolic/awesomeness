<?php

declare(strict_types=1);

namespace Prooph\EventStore\Internal\Data;

class PersistentSubscriptionCreateResult
{
    /** @var string */
    private $correlationId;
    /** @var string */
    private $reason;
    /** @var PersistentSubscriptionCreateStatus */
    private $status;

    /** @internal */
    public function __construct(string $correlationId, string $reason, PersistentSubscriptionCreateStatus $status)
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

    public function status(): PersistentSubscriptionCreateStatus
    {
        return $this->status;
    }
}
