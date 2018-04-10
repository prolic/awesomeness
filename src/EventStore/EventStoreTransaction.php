<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Prooph\EventStore\Internal\EventStoreTransactionConnection;
use Prooph\EventStore\Task\WriteResultTask;

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

    public function __construct(int $transactionId, ?UserCredentials $userCredentials, EventStoreTransactionConnection $connection)
    {
        $this->transactionId = $transactionId;
        $this->userCredentials = $userCredentials;
        $this->connection = $connection;
    }

    public function commitAsync(): WriteResultTask
    {
        if ($this->isRolledBack) {
            throw new \RuntimeException('Cannot commit a rolledback transaction');
        }

        if ($this->isCommitted) {
            throw new \RuntimeException('Transaction is already committed');
        }

        return $this->connection->commitTransactionAsync($this, $this->userCredentials);
    }

    /**
     * @param EventData[] $events
     * @return Task
     */
    public function writeAsync(array $events): Task
    {
        if ($this->isRolledBack) {
            throw new \RuntimeException('Cannot commit a rolledback transaction');
        }

        if ($this->isCommitted) {
            throw new \RuntimeException('Transaction is already committed');
        }

        return $this->connection->transactionalWriteAsync($this, $events);
    }

    public function rollback(): void
    {
        if ($this->isCommitted) {
            throw new \RuntimeException('Transaction is already committed');
        }

        $this->isRolledBack = true;
    }
}
