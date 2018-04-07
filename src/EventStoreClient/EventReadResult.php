<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

/** @internal */
class EventReadResult
{
    /** @var EventReadStatus */
    private $status;

    /** @var string */
    private $stream;

    /** @var int */
    private $eventNumber;

    /** @var RecordedEvent|null */
    private $event;

    /** @internal */
    public function __construct(EventReadStatus $status, string $stream, int $eventNumber, ?RecordedEvent $event)
    {
        $this->status = $status;
        $this->stream = $stream;
        $this->eventNumber = $eventNumber;
        $this->event = $event;
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

    public function event(): ?RecordedEvent
    {
        return $this->event;
    }
}
