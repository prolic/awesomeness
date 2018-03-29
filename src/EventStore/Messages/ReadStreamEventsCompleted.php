<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class ReadStreamEventsCompleted
{
    /** @var int */
    private $commitPosition;
    /** @var int */
    private $preparePosition;
    /** @var ResolvedEvent[] */
    private $events = [];
    /** @var int */
    private $nextCommitPosition;
    /** @var int */
    private $nextPreparePosition;
    /** ReadAllResult|null */
    private $result;
    /** @var string|null */
    private $error;

    public function __construct(
        int $commitPosition,
        int $preparePosition,
        array $events,
        int $nextCommitPosition,
        int $nextPreparePosition,
        ?ReadAllResult $result,
        ?string $error
    ) {
        foreach ($events as $event) {
            if (! $event instanceof ResolvedEvent) {
                throw new \InvalidArgumentException('Expected an array of ' . ResolvedEvent::class);
            }

            $this->events[] = $event;
        }

        $this->commitPosition = $commitPosition;
        $this->preparePosition = $preparePosition;
        $this->nextCommitPosition = $nextCommitPosition;
        $this->nextPreparePosition = $nextPreparePosition;
        $this->result = $result;
        $this->error = $error;
    }

    public function commitPosition(): int
    {
        return $this->commitPosition;
    }

    public function preparePosition(): int
    {
        return $this->preparePosition;
    }

    /**
     * @return ResolvedEvent[]
     */
    public function events(): array
    {
        return $this->events;
    }

    public function nextCommitPosition(): int
    {
        return $this->nextCommitPosition;
    }

    public function nextPreparePosition(): int
    {
        return $this->nextPreparePosition;
    }

    public function result()
    {
        return $this->result;
    }

    public function error(): ?string
    {
        return $this->error;
    }
}
