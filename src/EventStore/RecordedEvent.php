<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use DateTimeImmutable;

class RecordedEvent
{
    private $eventStreamId;
    private $eventId;
    private $eventNumber;
    private $eventType;
    private $data;
    private $metadata;
    private $isJson;
    private $created;

    /** @internal */
    public function __construct(
        string $eventStreamId,
        EventId $eventId,
        int $eventNumber,
        string $eventType,
        string $data,
        string $metadata,
        bool $isJson,
        DateTimeImmutable $created
    ) {
        $this->eventStreamId = $eventStreamId;
        $this->eventId = $eventId;
        $this->eventNumber = $eventNumber;
        $this->eventType = $eventType;
        $this->data = $data;
        $this->metadata = $metadata;
        $this->isJson = $isJson;
        $this->created = $created;
    }

    public function eventStreamId(): string
    {
        return $this->eventStreamId;
    }

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventNumber(): int
    {
        return $this->eventNumber;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function data(): string
    {
        return $this->data;
    }

    public function metadata(): string
    {
        return $this->metadata;
    }

    public function isJson(): bool
    {
        return $this->isJson;
    }

    public function created(): DateTimeImmutable
    {
        return $this->created;
    }
}
