<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Operations;

use Amp\Mysql\CommandResult;
use Amp\Postgres\Pool;
use Amp\Postgres\Statement;
use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\Projections\ProjectionEventTypes;
use Prooph\PostgresProjectionManager\Processing\CheckpointTag;

/** @internal */
class WriteCheckPointOperation
{
    /** @var Pool */
    private $pool;
    /** @var Statement */
    private $statement;

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
    }

    public function __invoke(string $checkpointStream, int $expectedVersion, array $state, CheckpointTag $checkpointTag): Generator
    {
        if (null === $this->statement || ! $this->statement->isAlive()) {
            $this->statement = yield $this->pool->prepare(<<<SQL
INSERT INTO events (event_id, event_number, event_type, data, meta_data, stream_name, is_json, updated) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?);
SQL
            );
        }

        $params[] = EventId::generate()->toString();
        $params[] = ++$expectedVersion;
        $params[] = ProjectionEventTypes::ProjectionCheckpoint;
        $params[] = \json_encode($state);
        $params[] = $checkpointTag->toJsonString();
        $params[] = $checkpointStream;
        $params[] = true;
        $params[] = DateTimeUtil::format(new DateTimeImmutable('NOW', new DateTimeZone('UTC')));

        /** @var CommandResult $result */
        $result = yield $this->statement->execute($params);

        if (0 === $result->affectedRows()) {
            throw new RuntimeException('Could not write checkpoint');
        }
    }
}
