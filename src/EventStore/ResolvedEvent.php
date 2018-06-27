<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Prooph\EventStore\Messages\ResolvedEvent as ResolvedEventMessage;
use Prooph\EventStore\Messages\ResolvedIndexedEvent as ResolvedIndexedEventMessage;

/**
 * A structure representing a single event or an resolved link event.
 */
class ResolvedEvent
{
    /**
     * The event, or the resolved link event if this is a link event
     * @var RecordedEvent
     */
    private $event;
    /**
     * The link event if this ResolvedEvent is a link event.
     * @var RecordedEvent|null
     */
    private $link;
    /**
     * Returns the event that was read or which triggered the subscription.
     *
     * If this ResolvedEvent represents a link event, the Link
     * will be the OriginalEvent otherwise it will be the event.
     * @var RecordedEvent
     */
    private $originalEvent;
    /**
     * Indicates whether this ResolvedEvent is a resolved link event.
     * @var bool
     */
    private $isResolved;
    /**
     * The logical position of the OriginalEvent
     * @var Position|null
     */
    private $originalPosition;

    /** @internal */
    public static function fromResolvedEventMessae(ResolvedEventMessage $event): ResolvedEvent
    {
        $self = new self();

        $self->event = new RecordedEvent($event->event());
        $self->link = $event->link() ? new RecordedEvent($event->link()) : null;
        $self->originalEvent = $self->link ?? $self->event;
        $self->isResolved = null !== $self->link;
        $self->originalPosition = new Position($event->commitPosition(), $event->preparePosition());

        return $self;
    }

    public static function fromResolvedIndexedEventMessage(ResolvedIndexedEventMessage $event): ResolvedEvent
    {
        $self = new self();

        $self->event = new RecordedEvent($event->event());
        $self->link = $event->link() ? new RecordedEvent($event->link()) : null;
        $self->originalEvent = $self->link ?? $self->event;
        $self->isResolved = null !== $self->link;
        $self->originalPosition = null;

        return $self;
    }

    private function __construct()
    {
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
        return $this->originalEvent;
    }

    public function isResolved(): bool
    {
        return $this->isResolved;
    }

    public function originalPosition(): Position
    {
        return $this->originalPosition;
    }

    public function originalStreamName(): string
    {
        return $this->originalEvent->streamName();
    }

    public function originalEventNumber(): int
    {
        return $this->originalEvent->eventNumber();
    }
}
