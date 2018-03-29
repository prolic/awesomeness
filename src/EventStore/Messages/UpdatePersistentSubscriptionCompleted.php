<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
final class UpdatePersistentSubscriptionCompleted
{
    /** @var UpdatePersistentSubscriptionResult */
    private $result;
    /** @var string|null */
    private $reason;

    public function __construct(UpdatePersistentSubscriptionResult $result, ?string $reason)
    {
        $this->result = $result;
        $this->reason = $reason;
    }

    public function result(): UpdatePersistentSubscriptionResult
    {
        return $this->result;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
