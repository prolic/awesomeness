<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Operations;

use Amp\Postgres\Connection;
use Amp\Postgres\Pool;
use Amp\Postgres\ResultSet;
use Amp\Postgres\Statement;
use Generator;
use Prooph\EventStore\Exception\RuntimeException;

/** @internal */
class LockOperation
{
    /** @var Pool */
    private $pool;
    /** @var Connection */
    private $lockConnection;
    /** @var Statement */
    private $acquireStatement;
    /** @var Statement */
    private $releaseStatement;

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
    }

    public function acquire(string $streamName): Generator
    {
        $newConnection = false;

        if (null === $this->lockConnection || ! $this->lockConnection->isAlive()) {
            $this->lockConnection = yield $this->pool->extractConnection();
            $newConnection = true;
        }

        if ($newConnection || null === $this->acquireStatement || ! $this->acquireStatement->isAlive()) {
            $this->acquireStatement = yield $this->lockConnection->prepare('SELECT PG_ADVISORY_LOCK(HASHTEXT(?)) as stream_lock;');
        }

        /** @var ResultSet $result */
        $result = yield $this->acquireStatement->execute([$streamName]);

        yield $result->advance(ResultSet::FETCH_OBJECT);
    }

    public function release(string $streamName): Generator
    {
        if (null === $this->lockConnection || ! $this->lockConnection->isAlive()) {
            return null;
        }

        if (null === $this->releaseStatement || ! $this->releaseStatement->isAlive()) {
            $this->releaseStatement = yield $this->lockConnection->prepare('SELECT PG_ADVISORY_UNLOCK(HASHTEXT(?)) as stream_lock;');
        }

        /** @var ResultSet $result */
        $result = yield $this->releaseStatement->execute([$streamName]);

        yield $result->advance(ResultSet::FETCH_OBJECT);

        $lock = $result->getCurrent()->stream_lock;

        if (! $lock) {
            throw new RuntimeException('Could not release lock for ' . $streamName);
        }
    }
}
