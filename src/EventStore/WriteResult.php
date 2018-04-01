<?php

declare(strict_types=1);

namespace Prooph\EventStore;

class WriteResult
{
    /** @var int */
    private $nextExpectedVersion;

    public function __construct(int $nextExpectedVersion)
    {
        $this->nextExpectedVersion = $nextExpectedVersion;
    }

    public function nextExpectedVersion(): int
    {
        return $this->nextExpectedVersion;
    }
}
