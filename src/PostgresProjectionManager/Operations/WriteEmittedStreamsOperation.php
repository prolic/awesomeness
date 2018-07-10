<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Operations;

use Amp\Mysql\CommandResult;
use Amp\Postgres\Pool;
use Amp\Postgres\Statement;
use Generator;
use Prooph\EventStore\Data\EventId;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\Projections\ProjectionEventTypes;

/** @internal */
class WriteEmittedStreamsOperation
{
    /** @var Pool */
    private $pool;

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
    }

    public function __invoke(string $emittedStream, array $unhandledTrackedStreams, int $expectedVersion): Generator
    {
        if (\count($unhandledTrackedStreams) === 0) {
            return null;
        }

        $sql = <<<SQL
INSERT INTO events (event_id, event_number, event_type, data, meta_data, stream_name, is_json, updated) 
    VALUES 
SQL;
        $sql .= \str_repeat('(?, ?, ?, ?, ?, ?, ?, ?), ', \count($unhandledTrackedStreams));
        $sql = \substr($sql, 0, -2) . ';';

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare($sql);

        foreach ($unhandledTrackedStreams as $stream) {
            $params[] = EventId::generate()->toString();
            $params[] = ++$expectedVersion;
            $params[] = ProjectionEventTypes::StreamTracked;
            $params[] = $stream;
            $params[] = '';
            $params[] = $emittedStream;
            $params[] = false;
            $params[] = DateTimeUtil::format(DateTimeUtil::utcNow());
        }

        /** @var CommandResult $result */
        $result = yield $statement->execute($params);

        if (0 === $result->affectedRows()) {
            throw new RuntimeException('Could not write emitted streams');
        }
    }
}
