<?php

declare(strict_types=1);

namespace Prooph\EventStore;

class ConditionalWriteResult
{
    /** @var ConditionalWriteStatus */
    private $status;
    /** @var int|null */
    private $nextExpectedVersion;
    /** @var Position|null */
    private $logPosition;

    public static function success(int $nextExpectedVersion, Position $logPosition): ConditionalWriteResult
    {
        return new self($nextExpectedVersion, $logPosition, ConditionalWriteStatus::succeeded());
    }

    public static function fail(ConditionalWriteStatus $status): ConditionalWriteResult
    {
        if ($status->equals(ConditionalWriteStatus::succeeded())) {
            throw new \DomainException('For successful write pass next expected version and log position');
        }

        return new self(null, null, $status);
    }

    private function __construct(?int $nextExpectedVersion, ?Position $logPosition, ConditionalWriteStatus $status)
    {
        $this->nextExpectedVersion = $nextExpectedVersion;
        $this->logPosition = $logPosition;
        $this->status = $status;
    }

    public function status(): ConditionalWriteStatus
    {
        return $this->status;
    }

    public function nextExpectedVersion(): ?int
    {
        return $this->nextExpectedVersion;
    }

    public function logPosition(): ?Position
    {
        return $this->logPosition;
    }
}
