<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Operations;

use Amp\Mysql\CommandResult;
use Amp\Postgres\Pool;
use Amp\Postgres\Statement;
use Generator;
use Prooph\EventStore\Exception\RuntimeException;

/** @internal */
class CreateStreamOperation
{
    /** @var Pool */
    private $pool;
    /** @var Statement */
    private $statement;

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
    }

    public function __invoke(string $streamName): Generator
    {
        if (null === $this->statement || ! $this->statement->isAlive()) {
            $this->statement = yield $this->pool->prepare(<<<SQL
INSERT INTO streams (stream_name, mark_deleted, deleted) VALUES (?, ?, ?);
SQL
            );
        }

        /** @var CommandResult $result */
        $result = yield $this->statement->execute([$streamName, 0, 0]);

        if (0 === $result->affectedRows()) {
            throw new RuntimeException('Could not create stream for ' . $streamName);
        }
    }
}
