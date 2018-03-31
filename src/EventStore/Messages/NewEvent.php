<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class NewEvent
{
    private $eventId;
    private $eventType;
    private $dataContentType;
    private $metadataContentType;
    private $data;
    private $metadata;

    /** @internal */
    public function __construct(string $eventId, string $eventType, int $dataContentType, int $metadataContentType, string $data, string $metadata)
    {
        $this->eventId = $eventId;
        $this->eventType = $eventType;
        $this->dataContentType = $dataContentType;
        $this->metadataContentType = $metadataContentType;
        $this->data = $data;
        $this->metadata = $metadata;
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
}
