<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class WriteEvents
{
    /** @var string */
    private $eventStreamId;
    /** @var int */
    private $expectedVersion;
    /** @var NewEvent[] */
    private $events;
    /** @var bool */
    private $requireMaster;

    /** @internal */
    public function __construct(string $eventStreamId, int $expectedVersion, iterable $events, bool $requireMaster)
    {
        $this->eventStreamId = $eventStreamId;
        $this->expectedVersion = $expectedVersion;

        foreach ($events as $__value) {
            if (! $__value instanceof NewEvent) {
                throw new \InvalidArgumentException('events expected an array of Prooph\EventStore\Messages\NewEvent');
            }
            $this->events[] = $__value;
        }

        $this->requireMaster = $requireMaster;
    }

    public function eventStreamId(): string
    {
        return $this->eventStreamId;
    }

    public function expectedVersion(): int
    {
        return $this->expectedVersion;
    }

    public function events(): array
    {
        return $this->events;
    }

    public function requireMaster(): bool
    {
        return $this->requireMaster;
    }
}
