<?php

declare(strict_types=1);

namespace Prooph\EventStore\Data;

class PersistentSubscriptionResolvedEvent
{
    /** @var int|null */
    private $retryCount;
    /** @var ResolvedEvent */
    private $event;

    /** @internal */
    public function __construct(ResolvedEvent $event, ?int $retryCount)
    {
        $this->event = $event;
        $this->retryCount = $retryCount;
    }

    public function retryCount(): ?int
    {
        return $this->retryCount;
    }

    public function event(): ResolvedEvent
    {
        return $this->event;
    }

    public function originalEvent(): ?EventRecord
    {
        return $this->event->originalEvent();
    }
}
