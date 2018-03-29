<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class ReadStreamEvents
{
    /** @var string */
    private $eventStreamId;
    /** @var int */
    private $fromEventNumber;
    /** @var int */
    private $maxCount;
    /** @var bool */
    private $resolveLinkTos;
    /** @var bool */
    private $requireMaster;

    public function __construct(string $eventStreamId, int $fromEventNumber, int $maxCount, bool $resolveLinkTos, bool $requireMaster)
    {
        $this->eventStreamId = $eventStreamId;
        $this->fromEventNumber = $fromEventNumber;
        $this->maxCount = $maxCount;
        $this->resolveLinkTos = $resolveLinkTos;
        $this->requireMaster = $requireMaster;
    }

    public function eventStreamId(): string
    {
        return $this->eventStreamId;
    }

    public function fromEventNumber(): int
    {
        return $this->fromEventNumber;
    }

    public function maxCount(): int
    {
        return $this->maxCount;
    }

    public function resolveLinkTos(): bool
    {
        return $this->resolveLinkTos;
    }

    public function requireMaster(): bool
    {
        return $this->requireMaster;
    }
}
