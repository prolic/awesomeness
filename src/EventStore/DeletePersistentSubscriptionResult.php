<?php

declare(strict_types=1);

namespace Prooph\EventStore;

class DeletePersistentSubscriptionResult
{
    /** @var string */
    private $correlationId;
    /** @var string */
    private $reason;
    /** @var DeletePersistentSubscriptionStatus */
    private $status;

    /** @internal */
    public function __construct(string $correlationId, string $reason, DeletePersistentSubscriptionStatus $status)
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

    public function status(): DeletePersistentSubscriptionStatus
    {
        return $this->status;
    }
}
