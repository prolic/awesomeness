<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Data;

class PersistentSubscriptionDeleteResult
{
    /** @var PersistentSubscriptionDeleteStatus */
    private $status;

    /** @internal */
    public function __construct(PersistentSubscriptionDeleteStatus $status)
    {
        $this->status = $status;
    }

    public function status(): PersistentSubscriptionDeleteStatus
    {
        return $this->status;
    }
}
