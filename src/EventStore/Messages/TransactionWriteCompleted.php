<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class TransactionWriteCompleted
{
    /** @var int */
    private $transactionId;
    /** @var OperationResult */
    private $result;
    /** @var string */
    private $message;

    public function __construct(int $transactionId, OperationResult $result, string $message)
    {
        $this->transactionId = $transactionId;
        $this->result = $result;
        $this->message = $message;
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
}
