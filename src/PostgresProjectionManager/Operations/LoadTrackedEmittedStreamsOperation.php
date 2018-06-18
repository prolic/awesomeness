<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Operations;

use Amp\Postgres\Pool;
use Amp\Postgres\ResultSet;
use Amp\Postgres\Statement;
use Generator;

/** @internal */
class LoadTrackedEmittedStreamsOperation
{
    public function __invoke(Pool $pool, string $emittedStream): Generator
    {
        /** @var Statement $statement */
        $statement = yield $pool->prepare(<<<SQL
SELECT
    data,
FROM
    events
WHERE stream_name = ?
ORDER BY event_number ASC
SQL
        );
        /** @var ResultSet $result */
        $result = yield $statement->execute([$emittedStream]);

        $trackedEmittedStreams = [];

        while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
            $trackedEmittedStreams[] = $result->getCurrent()->data;
        }

        return $trackedEmittedStreams;
    }
}
