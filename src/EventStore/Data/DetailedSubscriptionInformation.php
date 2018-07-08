<?php

declare(strict_types=1);

namespace Prooph\EventStore\Data;

class DetailedSubscriptionInformation
{
    /** @var PersistentSubscriptionSettings */
    private $settings;
    /** @var string */
    private $eventStreamId;
    /** @var string */
    private $groupName;
    /** @var string */
    private $status;
    /** @var float */
    private $averageItemsPerSecond;
    /** @var int */
    private $totalItemsProcessed;
    /** @var int */
    private $countSinceLastMeasurement;
    /** @var int */
    private $lastProcessedEventNumber;
    /** @var int */
    private $lastKnownEventNumber;
    /** @var int */
    private $readBufferCount;
    /** @var int */
    private $liveBufferCount;
    /** @var int */
    private $retryBufferCount;
    /** @var int */
    private $totalInFlightMessages;

    /** @internal */
    public function __construct(
        PersistentSubscriptionSettings $settings,
        string $eventStreamId,
        string $groupName,
        string $status,
        float $averageItemsPerSecond,
        int $totalItemsProcessed,
        int $countSinceLastMeasurement,
        int $lastProcessedEventNumber,
        int $lastKnownEventNumber,
        int $readBufferCount,
        int $liveBufferCount,
        int $retryBufferCount,
        int $totalInFlightMessages
    ) {
        $this->settings = $settings;
        $this->eventStreamId = $eventStreamId;
        $this->groupName = $groupName;
        $this->status = $status;
        $this->averageItemsPerSecond = $averageItemsPerSecond;
        $this->totalItemsProcessed = $totalItemsProcessed;
        $this->countSinceLastMeasurement = $countSinceLastMeasurement;
        $this->lastProcessedEventNumber = $lastProcessedEventNumber;
        $this->lastKnownEventNumber = $lastKnownEventNumber;
        $this->readBufferCount = $readBufferCount;
        $this->liveBufferCount = $liveBufferCount;
        $this->retryBufferCount = $retryBufferCount;
        $this->totalInFlightMessages = $totalInFlightMessages;
    }

    public function eventStreamId(): string
    {
        return $this->eventStreamId;
    }

    public function groupName(): string
    {
        return $this->groupName;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function averageItemsPerSecond(): float
    {
        return $this->averageItemsPerSecond;
    }

    public function totalItemsProcessed(): int
    {
        return $this->totalItemsProcessed;
    }

    public function countSinceLastMeasurement(): int
    {
        return $this->countSinceLastMeasurement;
    }

    public function lastProcessedEventNumber(): int
    {
        return $this->lastProcessedEventNumber;
    }

    public function lastKnownEventNumber(): int
    {
        return $this->lastKnownEventNumber;
    }

    public function readBufferCount(): int
    {
        return $this->readBufferCount;
    }

    public function liveBufferCount(): int
    {
        return $this->liveBufferCount;
    }

    public function retryBufferCount(): int
    {
        return $this->retryBufferCount;
    }

    public function totalInFlightMessages(): int
    {
        return $this->totalInFlightMessages;
    }
}
