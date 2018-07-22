<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStoreClient\EventStoreSubscription;
use Prooph\EventStoreClient\ClientOperations\VolatileSubscriptionOperation;

/** @internal */
class VolatileEventStoreSubscription extends EventStoreSubscription
{
    /** @var VolatileSubscriptionOperation */
    private $subscriptionOperation;

    public function __construct(
        VolatileSubscriptionOperation $subscriptionOperation,
        string $streamId,
        int $lastCommitPosition,
        ?int $lastEventNumber
    ) {
        parent::__construct($streamId, $lastCommitPosition, $lastEventNumber);

        $this->subscriptionOperation = $subscriptionOperation;
    }

    public function unsubscribe(): void
    {
        $this->subscriptionOperation->unsubscribe();
    }
}
