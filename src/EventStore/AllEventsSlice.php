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
    /** @var Messages\ResolvedEvent[] */
    private $events = [];

    /** @internal */
    public function __construct(ReadDirection $readDirection, Position $fromPosition, Position $nextPosition, array $events)
    {
        $this->readDirection = $readDirection;
        $this->fromPosition = $fromPosition;
        $this->nextPosition = $nextPosition;

        foreach ($events as $event) {
            $this->events[] = ResolvedEvent::fromResolvedEvent($event);
        }
    }

    public function isEndOfStream(): bool
    {
        return count($this->events) === 0;
    }
}
