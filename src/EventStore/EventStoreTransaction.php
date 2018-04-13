<?php

declare(strict_types=1);

namespace Prooph\EventStore;

class EventStoreTransaction
{
    /** @var int */
    private $transactionId;
    /** @var UserCredentials|null */
    private $userCredentials;
    /** @var EventStoreTransactionConnection */
    private $connection;
    /** @var bool */
    private $isRolledBack;
    /** @var bool */
    private $isCommitted;

    public function __construct(
        int $transactionId,
        ?UserCredentials $userCredentials,
        EventStoreTransactionConnection $connection
    ) {
        $this->transactionId = $transactionId;
        $this->userCredentials = $userCredentials;
        $this->connection = $connection;
    }

    public function commit(): WriteResult
    {
        if ($this->isRolledBack) {
            throw new \RuntimeException('Cannot commit a rolledback transaction');
        }

        if ($this->isCommitted) {
            throw new \RuntimeException('Transaction is already committed');
        }

        return $this->connection->commitTransaction($this, $this->userCredentials);
    }

    /**
     * @param EventData[] $events
     * @return void
     */
    public function writeAsync(array $events): void
    {
        if ($this->isRolledBack) {
            throw new \RuntimeException('Cannot commit a rolledback transaction');
        }

        if ($this->isCommitted) {
            throw new \RuntimeException('Transaction is already committed');
        }

        $this->connection->transactionalWrite($this, $events, $this->userCredentials);
    }

    public function rollback(): void
    {
        if ($this->isCommitted) {
            throw new \RuntimeException('Transaction is already committed');
        }

        $this->isRolledBack = true;
    }
}
