<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class CreatePersistentSubscriptionCompleted
{
    /** @var CreatePersistentSubscriptionResult */
    private $result;
    /** @var string|null */
    private $reason;

    /** @internal */
    public function __construct(CreatePersistentSubscriptionResult $result, ?string $reason)
    {
        $this->result = $result;
        $this->reason = $reason;
    }

    public function result(): CreatePersistentSubscriptionResult
    {
        return $this->result;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
