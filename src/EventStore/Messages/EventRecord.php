<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

final class EventRecord
{
    private $eventStreamId;
    private $eventNumber;
    private $eventId;
    private $eventType;
    private $dataContentType;
    private $metadataContentType;
    private $data;
    private $metadata;
    private $created;
    private $createdEpoch;

    public function __construct(string $eventStreamId, int $eventNumber, string $eventId, string $eventType, int $dataContentType, int $metadataContentType, string $data, string $metadata, ?string $created, ?int $createdEpoch)
    {
        $this->eventStreamId = $eventStreamId;
        $this->eventNumber = $eventNumber;
        $this->eventId = $eventId;
        $this->eventType = $eventType;
        $this->dataContentType = $dataContentType;
        $this->metadataContentType = $metadataContentType;
        $this->data = $data;
        $this->metadata = $metadata;
        $this->created = $created;
        $this->createdEpoch = $createdEpoch;
    }

    public function eventStreamId(): string
    {
        return $this->eventStreamId;
    }

    public function eventNumber(): int
    {
        return $this->eventNumber;
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function dataContentType(): int
    {
        return $this->dataContentType;
    }

    public function metadataContentType(): int
    {
        return $this->metadataContentType;
    }

    public function data(): string
    {
        return $this->data;
    }

    public function metadata(): string
    {
        return $this->metadata;
    }

    public function created(): ?string
    {
        return $this->created;
    }

    public function createdEpoch(): ?int
    {
        return $this->createdEpoch;
    }
}
