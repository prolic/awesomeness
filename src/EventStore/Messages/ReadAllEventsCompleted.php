<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class ReadAllEventsCompleted
{
    private $commitPosition;
    private $preparePosition;
    private $maxCount;
    private $resolveLinkTos;
    private $requireMaster;

    public function __construct(int $commitPosition, int $preparePosition, int $maxCount, bool $resolveLinkTos, bool $requireMaster)
    {
        $this->commitPosition = $commitPosition;
        $this->preparePosition = $preparePosition;
        $this->maxCount = $maxCount;
        $this->resolveLinkTos = $resolveLinkTos;
        $this->requireMaster = $requireMaster;
    }

    public function commitPosition(): int
    {
        return $this->commitPosition;
    }

    public function preparePosition(): int
    {
        return $this->preparePosition;
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
