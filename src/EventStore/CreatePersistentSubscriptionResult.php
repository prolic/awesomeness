<?php

declare(strict_types=1);

namespace Prooph\EventStore;

class CreatePersistentSubscriptionResult
{
    /** @var string */
    private $correlationId;
    /** @var string */
    private $reason;
    /** @var CreatePersistentSubscriptionStatus */
    private $status;

    /** @internal */
    public function __construct(string $correlationId, string $reason, CreatePersistentSubscriptionStatus $status)
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

    public function status(): CreatePersistentSubscriptionStatus
    {
        return $this->status;
    }
}
