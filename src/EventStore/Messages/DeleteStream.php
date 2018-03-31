<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class DeleteStream
{
    /** @var string */
    private $eventStreamId;
    /** @var bool */
    private $hardDelete;

    public function __construct(string $eventStreamId, bool $hardDelete = false)
    {
        $this->eventStreamId = $eventStreamId;
        $this->hardDelete = $hardDelete;
    }

    public function eventStreamId(): string
    {
        return $this->eventStreamId;
    }

    public function hardDelete(): bool
    {
        return $this->hardDelete;
    }
}
