<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

class WriteEventsCompleted
{
    /** @var OperationResult */
    private $result;
    /** @var string */
    private $message;
    /** @var int */
    private $firstEventNumber;
    /** @var int */
    private $lastEventNumber;
    /** @var int|null */
    private $preparePosition;
    /** @var int|null */
    private $commitPosition;
    /** @var int|null */
    private $currentVersion;

    public function __construct(OperationResult $result, string $message, int $firstEventNumber, int $lastEventNumber, ?int $preparePosition, ?int $commitPosition, ?int $currentVersion)
    {
        $this->result = $result;
        $this->message = $message;
        $this->firstEventNumber = $firstEventNumber;
        $this->lastEventNumber = $lastEventNumber;
        $this->preparePosition = $preparePosition;
        $this->commitPosition = $commitPosition;
        $this->currentVersion = $currentVersion;
    }

    public function result(): OperationResult
    {
        return $this->result;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function firstEventNumber(): int
    {
        return $this->firstEventNumber;
    }

    public function lastEventNumber(): int
    {
        return $this->lastEventNumber;
    }

    public function preparePosition(): ?int
    {
        return $this->preparePosition;
    }

    public function commitPosition(): ?int
    {
        return $this->commitPosition;
    }

    public function currentVersion(): ?int
    {
        return $this->currentVersion;
    }
}
