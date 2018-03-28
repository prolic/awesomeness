<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

final class DeleteStream
{
    private $eventStreamId;
    private $expectedVersion;
    private $requireMaster;
    private $hardDelete;

    public function __construct(string $eventStreamId, int $expectedVersion, bool $requireMaster, ?bool $hardDelete)
    {
        $this->eventStreamId = $eventStreamId;
        $this->expectedVersion = $expectedVersion;
        $this->requireMaster = $requireMaster;
        $this->hardDelete = $hardDelete;
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

    public function hardDelete(): ?bool
    {
        return $this->hardDelete;
    }
}
