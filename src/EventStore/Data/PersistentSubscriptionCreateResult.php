<?php

declare(strict_types=1);

namespace Prooph\EventStore\Internal\Data;

class PersistentSubscriptionCreateResult
{
    /** @var PersistentSubscriptionCreateStatus */
    private $status;

    /** @internal */
    public function __construct(PersistentSubscriptionCreateStatus $status)
    {
        $this->status = $status;
    }

    public function status(): PersistentSubscriptionCreateStatus
    {
        return $this->status;
    }
}
