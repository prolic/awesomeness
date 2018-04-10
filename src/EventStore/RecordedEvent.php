<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use DateTimeImmutable;

class RecordedEvent
{
    /** @var string */
    private $streamId;
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
    public function __construct(
        string $streamId,
        EventId $eventId,
        int $eventNumber,
        string $eventType,
        string $data,
        string $metaData,
        bool $isJson,
        DateTimeImmutable $created
    ) {
        $this->streamId = $streamId;
        $this->eventId = $eventId;
        $this->eventNumber = $eventNumber;
        $this->eventType = $eventType;
        $this->data = $data;
        $this->metaData = $metaData;
        $this->isJson = $isJson;
        $this->created = $created;
    }

    public function streamId(): string
    {
        return $this->streamId;
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
