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

    public function getOperation(): SubscriptionOperation
    {
        return $this->operation;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getCreatedTime(): DateTimeImmutable
    {
        return $this->createdTime;
    }

    public function getConnectionId(): string
    {
        return $this->connectionId;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function isSubscribed(): bool
    {
        return $this->isSubscribed;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function getLastUpdated(): DateTimeImmutable
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

    public function setRetryCount(int $retryCount): void
    {
        $this->retryCount = $retryCount;
    }

    public function setLastUpdated(DateTimeImmutable $lastUpdated): void
    {
        $this->lastUpdated = $lastUpdated;
    }
}
