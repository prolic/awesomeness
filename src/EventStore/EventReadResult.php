<?php

declare(strict_types=1);

namespace Prooph\EventStore;

class EventReadResult
{
    /** @var EventReadStatus */
    private $status;

    /** @var string */
    private $stream;

    /** @var int */
    private $eventNumber;

    /** @var ResolvedEvent|null */
    private $event;

    public function __construct(EventReadStatus $status, string $stream, int $eventNumber, ?ResolvedIndexedEvent $event)
    {
        $this->status = $status;
        $this->stream = $stream;
        $this->eventNumber = $eventNumber;
        $this->event = $status->equals(EventReadStatus::success()) ? ResolvedEvent::fromResolvedIndexedEvent($event) : null;
    }

    public function status(): EventReadStatus
    {
        return $this->status;
    }

    public function stream(): string
    {
        return $this->stream;
    }

    public function eventNumber(): int
    {
        return $this->eventNumber;
    }

    public function event(): ?ResolvedEvent
    {
        return $this->event;
    }
}
