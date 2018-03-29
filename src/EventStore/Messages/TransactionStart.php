<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

class TransactionStart
{
    /** @var string */
    private $eventStreamId;
    /** @var int */
    private $expectedVersion;
    /** @var bool */
    private $requireMaster;

    public function __construct(string $eventStreamId, int $expectedVersion, bool $requireMaster)
    {
        $this->eventStreamId = $eventStreamId;
        $this->expectedVersion = $expectedVersion;
        $this->requireMaster = $requireMaster;
    }

    public function eventStreamId(): string
    {
        return $this->eventStreamId;
    }

    public function expectedVersion(): int
    {
        return $this->expectedVersion;
    }

    public function requireMaster(): bool
    {
        return $this->requireMaster;
    }
}
