<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use DateTimeImmutable;
use Prooph\EventStore\Messages\EventRecord;

class RecordedEvent
{
    /** @var string */
    private $streamName;
    /** @var EventId */
    private $eventId;
    /** @var int */
    private $eventNumber;
    /** @var string */
    private $eventType;
    /** @var string */
    private $data;
    /** @var string */
    private $metaData;
    /** @var bool */
    private $isJson;
    /** @var DateTimeImmutable */
    private $created;

    /** @internal */
    public function __construct(EventRecord $eventRecord)
    {
        $this->streamName = $eventRecord->eventStreamId();
        $this->eventId = $eventRecord->eventId();
        $this->eventNumber = $eventRecord->eventNumber();
        $this->eventType = $eventRecord->eventType();
        $this->data = $eventRecord->data();
        $this->metaData = $eventRecord->metadata();
        $this->isJson = $eventRecord->isJson();
        $this->created = $eventRecord->created();
    }

    public function streamName(): string
    {
        return $this->streamName;
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

    public function metaData(): string
    {
        return $this->metaData;
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
