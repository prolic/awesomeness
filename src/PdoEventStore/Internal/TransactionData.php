<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\Internal;

use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Data\EventData;

/** @internal  */
class TransactionData
{
    /** @var EventData[] */
    private $events;
    /** @var UserCredentials|null */
    private $userCredentials;

    public function __construct(array $events, ?UserCredentials $userCredentials)
    {
        $this->events = $events;
        $this->userCredentials = $userCredentials;
    }

    public function events(): array
    {
        return $this->events;
    }

    public function userCredentials(): ?UserCredentials
    {
        return $this->userCredentials;
    }
}
