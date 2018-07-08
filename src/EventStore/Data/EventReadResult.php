<?php

declare(strict_types=1);

namespace Prooph\EventStore\Data;

use Prooph\EventStore\Internal\Messages\ResolvedIndexedEvent;
use Prooph\EventStoreClient\Internal\EventMessageConverter;

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

    /** @internal */
    public function __construct(EventReadStatus $status, string $stream, int $eventNumber, ?ResolvedIndexedEvent $event)
    {
        $this->status = $status;
        $this->stream = $stream;
        $this->eventNumber = $eventNumber;
        $this->event = $event ? EventMessageConverter::convertResolvedIndexedEventMessageToResolvedEvent($event) : null;
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
