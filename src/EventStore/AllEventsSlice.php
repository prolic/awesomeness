<?php

declare(strict_types=1);

namespace Prooph\EventStore;

class AllEventsSlice
{
    /** @var ReadDirection */
    private $readDirection;
    /** @var Position */
    private $fromPosition;
    /** @var Position */
    private $nextPosition;
    /** @var RecordedEvent[] */
    private $events;

    /** @internal */
    public function __construct(ReadDirection $readDirection, Position $fromPosition, Position $nextPosition, array $events)
    {
        $this->readDirection = $readDirection;
        $this->fromPosition = $fromPosition;
        $this->nextPosition = $nextPosition;
        $this->events = $events;
    }

    public function readDirection(): ReadDirection
    {
        return $this->readDirection;
    }

    public function fromPosition(): Position
    {
        return $this->fromPosition;
    }

    public function nextPosition(): Position
    {
        return $this->nextPosition;
    }

    /**
     * @return RecordedEvent[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function isEndOfStream(): bool
    {
        return count($this->events) === 0;
    }
}
