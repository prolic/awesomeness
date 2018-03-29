<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

class TransactionCommitCompleted
{
    /** @var int */
    private $transactionId;
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

    public function __construct(int $transactionId, OperationResult $result, string $message, int $firstEventNumber, int $lastEventNumber, ?int $preparePosition, ?int $commitPosition)
    {
        $this->transactionId = $transactionId;
        $this->result = $result;
        $this->message = $message;
        $this->firstEventNumber = $firstEventNumber;
        $this->lastEventNumber = $lastEventNumber;
        $this->preparePosition = $preparePosition;
        $this->commitPosition = $commitPosition;
    }

    public function transactionId(): int
    {
        return $this->transactionId;
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
}
