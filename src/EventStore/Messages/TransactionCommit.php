<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

class TransactionCommit
{
    private $transactionId;
    private $requireMaster;

    public function __construct(int $transactionId, bool $requireMaster)
    {
        $this->transactionId = $transactionId;
        $this->requireMaster = $requireMaster;
    }

    public function transactionId(): int
    {
        return $this->transactionId;
    }

    public function requireMaster(): bool
    {
        return $this->requireMaster;
    }
}
