<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Operations;

use Amp\Postgres\Pool;
use Amp\Postgres\ResultSet;
use Amp\Postgres\Statement;
use Error;
use Generator;
use Prooph\PostgresProjectionManager\Exception\StreamNotFound;

/** @internal */
class LoadLatestCheckpointOperation
{
    public function __invoke(Pool $pool, string $checkpointStream): Generator
    {
        try {
            $sql = <<<SQL
SELECT
    e2.event_id as event_id,
    e1.event_number as event_number,
    COALESCE(e1.event_type, e2.event_type) as event_type,
    COALESCE(e1.data, e2.data) as data,
    COALESCE(e1.meta_data, e2.meta_data) as meta_data,
    COALESCE(e1.is_json, e2.is_json) as is_json,
    COALESCE(e1.updated, e2.updated) as updated
FROM
    events e1
LEFT JOIN events e2
    ON (e1.link_to_stream_name = e2.stream_name AND e1.link_to_event_number = e2.event_number)
WHERE e1.stream_name = ?
ORDER BY e1.event_number DESC
LIMIT 1;
SQL;
            /** @var Statement $statement */
            $statement = yield $pool->prepare($sql);

            /** @var ResultSet $result */
            $result = yield $statement->execute([$checkpointStream]);

            if (yield $result->advance(ResultSet::FETCH_OBJECT)) {
                $data = $result->getCurrent();
                $state = \json_decode($data->data, true);
                $streamPositions = \json_decode($data->meta_data, true);

                if (0 !== \json_last_error()) {
                    throw new Error('Could not json decode checkpoint');
                }

                return new LoadLatestCheckpointResult($state, $streamPositions['$s']);
            }
        } catch (StreamNotFound $e) {
            // ignore, no checkpoint found
        }
    }
}
