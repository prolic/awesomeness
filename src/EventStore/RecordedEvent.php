<?php

declare(strict_types=1);

namespace Prooph\EventStore;

final class RecordedEvent
{
    private $eventStreamId;
    private $eventId;
    private $eventNumber;
    private $eventType;
    private $data;
    private $metadata;
    private $isJson;
    private $created;
    private $createdEpoch;

    public function __construct(string $eventStreamId, EventId $eventId, int $eventNumber, string $eventType, string $data, string $metadata, bool $isJson, string $created, int $createdEpoch)
    {
        $this->eventStreamId = $eventStreamId;
        $this->eventId = $eventId;
        $this->eventNumber = $eventNumber;
        $this->eventType = $eventType;
        $this->data = $data;
        $this->metadata = $metadata;
        $this->isJson = $isJson;
        $this->created = $created;
        $this->createdEpoch = $createdEpoch;
    }

    /** @internal */
    public static function fromRecordedEvent(Messages\EventRecord $systemRecord): RecordedEvent
    {
        return new self(
            $systemRecord->eventStreamId(),
            EventId::fromString($systemRecord->eventId()),
            $systemRecord->eventNumber(),
            $systemRecord->eventType(),
            $systemRecord->data(),
            $systemRecord->metadata(),
            $systemRecord->dataContentType() === 1,
            $systemRecord->created(),
            $systemRecord->createdEpoch()
        );
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

    public function created(): string
    {
        return $this->created;
    }

    public function createdEpoch(): int
    {
        return $this->createdEpoch;
    }
}
