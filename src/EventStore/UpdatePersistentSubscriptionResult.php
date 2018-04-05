<?php

declare(strict_types=1);

namespace Prooph\EventStore;

class UpdatePersistentSubscriptionResult
{
    /** @var string */
    private $correlationId;
    /** @var string */
    private $reason;
    /** @var UpdatePersistentSubscriptionStatus */
    private $status;

    /** @internal */
    public function __construct(string $correlationId, string $reason, UpdatePersistentSubscriptionStatus $status)
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

    public function status(): UpdatePersistentSubscriptionStatus
    {
        return $this->status;
    }
}
