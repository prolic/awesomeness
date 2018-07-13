<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Event;

class ListenerHandler
{
    /** @var callable */
    private $listener;

    public function __construct(callable $listener)
    {
        $this->listener = $listener;
    }

    public function callback(): callable
    {
        return $this->listener;
    }
}
