<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

use DateTimeImmutable;
use Prooph\EventStore\EventId;

class EventRecord
{
    /** @var string */
    private $eventStreamId;
    /** @var int */
    private $eventNumber;
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
    /** @var DateTimeImmutable */
    private $created;

    public function __construct(
        string $eventStreamId,
        int $eventNumber,
        EventId $eventId,
        string $eventType,
        bool $isJson,
        string $data,
        string $metadata,
        DateTimeImmutable $created
    ) {
        $this->eventStreamId = $eventStreamId;
        $this->eventNumber = $eventNumber;
        $this->eventId = $eventId;
        $this->eventType = $eventType;
        $this->isJson = $isJson;
        $this->data = $data;
        $this->metadata = $metadata;
        $this->created = $created;
    }

    public function eventStreamId(): string
    {
        return $this->eventStreamId;
    }

    public function eventNumber(): int
    {
        return $this->eventNumber;
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

    public function created(): DateTimeImmutable
    {
        return $this->created;
    }
}
