<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use DateTimeImmutable;
use Prooph\EventStore\Internal\DateTimeUtil;
use Ramsey\Uuid\Uuid;

/** @internal */
class OperationItem
{
    // @todo: maybe we don't need this here
    // private static long _nextSeqNo = -1;
    // public readonly long SeqNo = Interlocked.Increment(ref _nextSeqNo);

    /** @var ClientOperation */
    private $operation;
    /** @var int */
    private $maxRetries;
    /** @var DateTimeImmutable */
    private $timeout;
    /** @var DateTimeImmutable */
    private $created;
    /** @var string */
    private $correlationId;
    /** @var int */
    private $retryCount;
    /** @var DateTimeImmutable */
    private $lastUpdated;

    public function __construct(ClientOperation $operation, int $maxRetries, DateTimeImmutable $timeout)
    {
        $this->operation = $operation;
        $this->maxRetries = $maxRetries;
        $this->timeout = $timeout;
        $this->created = DateTimeUtil::utcNow();
        $this->correlationId = \str_replace('-', '', Uuid::uuid4()->toString());
        $this->retryCount = 0;
        $this->lastUpdated = $this->created;
    }

    public function operation(): ClientOperation
    {
        return $this->operation;
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function timeout(): DateTimeImmutable
    {
        return $this->timeout;
    }

    public function created(): DateTimeImmutable
    {
        return $this->created;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function retryCount(): int
    {
        return $this->retryCount;
    }

    public function incRetryCount(): void
    {
        ++$this->retryCount;
    }

    public function lastUpdated(): DateTimeImmutable
    {
        return $this->lastUpdated;
    }

    /**
     * @param DateTimeImmutable $lastUpdated
     */
    public function setLastUpdated(DateTimeImmutable $lastUpdated): void
    {
        $this->lastUpdated = $lastUpdated;
    }
}
