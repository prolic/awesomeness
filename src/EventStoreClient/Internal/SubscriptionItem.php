<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use DateTimeImmutable;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStoreClient\Internal\ClientOperations\SubscriptionOperation;

/** @internal  */
class SubscriptionItem
{
    /** @var SubscriptionOperation */
    private $operation;
    /** @var int */
    private $maxRetries;
    /** @var int */
    private $timeout;
    /** @var DateTimeImmutable */
    private $createdTime;

    /** @var string */
    private $connectionId;
    /** @var string */
    private $correlationId;
    /** @var bool */
    private $isSubscribed;
    /** @var int */
    private $retryCount;
    /** @var DateTimeImmutable */
    private $lastUpdated;

    public function __construct(SubscriptionOperation $operation, int $maxRetries, int $timeout)
    {
        $this->operation = $operation;
        $this->maxRetries = $maxRetries;
        $this->timeout = $timeout;
        $this->createdTime = DateTimeUtil::utcNow();
        $this->correlationId = CorrelationIdGenerator::generate();
        $this->retryCount = 0;
        $this->lastUpdated = $this->createdTime;
    }

    public function operation(): SubscriptionOperation
    {
        return $this->operation;
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    public function createdTime(): DateTimeImmutable
    {
        return $this->createdTime;
    }

    public function connectionId(): string
    {
        return $this->connectionId;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function isSubscribed(): bool
    {
        return $this->isSubscribed;
    }

    public function retryCount(): int
    {
        return $this->retryCount;
    }

    public function lastUpdated(): DateTimeImmutable
    {
        return $this->lastUpdated;
    }

    public function setConnectionId(string $connectionId): void
    {
        $this->connectionId = $connectionId;
    }

    public function setCorrelationId(string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    public function setIsSubscribed(bool $isSubscribed): void
    {
        $this->isSubscribed = $isSubscribed;
    }

    public function incRetryCount(): void
    {
        ++$this->retryCount;
    }

    public function setLastUpdated(DateTimeImmutable $lastUpdated): void
    {
        $this->lastUpdated = $lastUpdated;
    }
}
