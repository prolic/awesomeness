<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\Internal;

/** @internal  */
class LockData
{
    /** @var string */
    private $stream;
    /** @var int */
    private $transactionId;
    /** @var int */
    private $expectedVersion;
    /** @var int */
    private $lockCounter;

    public function __construct(string $stream, int $transactionId, int $expectedVersion, int $lockCounter)
    {
        $this->stream = $stream;
        $this->transactionId = $transactionId;
        $this->expectedVersion = $expectedVersion;
        $this->lockCounter = $lockCounter;
    }

    public function stream(): string
    {
        return $this->stream;
    }

    public function transactionId(): int
    {
        return $this->transactionId;
    }

    public function expectedVersion(): int
    {
        return $this->expectedVersion;
    }

    public function lockCounter(): int
    {
        return $this->lockCounter;
    }
}
