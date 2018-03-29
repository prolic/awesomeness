<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class ReadEvent
{
    /** @var string */
    private $eventStreamId;
    /** @var int */
    private $eventNumber;
    /** @var bool */
    private $resolveLinkTos;
    /** @var bool */
    private $requireMaster;

    public function __construct(string $eventStreamId, int $eventNumber, bool $resolveLinkTos, bool $requireMaster)
    {
        $this->eventStreamId = $eventStreamId;
        $this->eventNumber = $eventNumber;
        $this->resolveLinkTos = $resolveLinkTos;
        $this->requireMaster = $requireMaster;
    }

    public function eventStreamId(): string
    {
        return $this->eventStreamId;
    }

    public function eventNumber(): int
    {
        return $this->eventNumber;
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
