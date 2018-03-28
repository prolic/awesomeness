<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Prooph\EventStore\Messages\ClientOperations\ResolvedEvent as ResolvedEventInterface;

final class ResolvedEvent implements ResolvedEventInterface
{
    private $event;
    private $link;
    private $originalPosition;

    public static function fromResolvedEvent(Messages\ResolvedEvent $event): ResolvedEvent
    {
        return new self(
            RecordedEvent::fromRecordedEvent($event->event()),
            RecordedEvent::fromRecordedEvent($event->link()),
            new Position($event->commitPosition(), $event->preparePosition())
        );
    }

    public static function fromResolvedIndexedEvent(ResolvedIndexedEvent $event)
    {
        return new self(
            RecordedEvent::fromRecordedEvent($event->event()),
            $event->link() ? RecordedEvent::fromRecordedEvent($event->link()) : null,
            null
        );
    }

    private function __construct(RecordedEvent $event, ?RecordedEvent $link, ?Position $originalPosition)
    {
        $this->event = $event;
        $this->link = $link;
        $this->originalPosition = $originalPosition;
    }

    public function event(): RecordedEvent
    {
        return $this->event;
    }

    public function link(): ?RecordedEvent
    {
        return $this->link;
    }

    public function originalEvent(): RecordedEvent
    {
        return null !== $this->link ? $this->link : $this->event;
    }

    public function isResolved(): bool
    {
        return null !== $this->link && null !== $this->event;
    }

    public function originalStreamId(): string
    {
        return $this->originalEvent()->eventStreamId();
    }

    public function originalEventNumber(): int
    {
        return $this->originalEvent()->eventNumber();
    }

    public function originalPosition(): ?Position
    {
        return $this->originalPosition;
    }
}
