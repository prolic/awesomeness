<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Processing;

use DateTimeImmutable;
use Prooph\EventStore\EventId;

class ResolvedEvent
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
    /** @var string */
    private $streamMetadata;

    public function __construct(
        string $streamName,
        EventId $eventId,
        int $eventNumber,
        string $eventType,
        string $data,
        string $metaData,
        bool $isJson,
        DateTimeImmutable $created,
        string $streamMetadata
    ) {
        $this->streamName = $streamName;
        $this->eventId = $eventId;
        $this->eventNumber = $eventNumber;
        $this->eventType = $eventType;
        $this->data = $data;
        $this->metaData = $metaData;
        $this->isJson = $isJson;
        $this->created = $created;
        $this->streamMetadata = $streamMetadata;
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

    public function streamMetadata(): string
    {
        return $this->streamMetadata;
    }
}
