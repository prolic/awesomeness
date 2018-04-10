<?php

declare(strict_types=1);

namespace Prooph\EventStore;

final class EventData
{
    /** @var EventId */
    private $eventId;
    /** @var string */
    private $eventType;
    /** @var bool */
    private $isJson;
    /** @var string */
    private $data;
    /** @var string */
    private $metadata;

    public function __construct(EventId $eventId, string $eventType, bool $isJson, string $data, string $metadata)
    {
        $this->eventId = $eventId;
        $this->eventType = $eventType;
        $this->isJson = $isJson;
        $this->data = $data;
        $this->metadata = $metadata;
    }

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function isJson(): bool
    {
        return $this->isJson;
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
