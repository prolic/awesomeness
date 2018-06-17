<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Operations;

use Amp\Postgres\Pool;
use Amp\Postgres\ResultSet;
use Amp\Postgres\Statement;
use Error;
use Generator;
use Prooph\EventStore\Internal\Principal;
use Prooph\EventStore\ProjectionManagement\Internal\ProjectionConfig;
use Prooph\EventStore\Projections\ProjectionEventTypes;
use Prooph\EventStore\Projections\ProjectionMode;

/** @internal */
class LoadConfigOperation
{
    private const DefaultCheckpointHandledThreshold = 4000;
    private const DefaultCheckpointUnhandledBytesThreshold = 10000000;
    private const DefaultPendingEventsThreshold = 5000;
    private const MinCheckpointAfterMs = 100;
    private const MaxMaxWriteBatchLength = 5000;

    public function __invoke(Pool $pool, string $projectionStream): Generator
    {
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
ORDER BY e1.event_number ASC
SQL;
        /** @var Statement $statement */
        $statement = yield $pool->prepare($sql);

        /** @var ResultSet $result */
        $result = yield $statement->execute([$projectionStream]);

        while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
            $event = $result->getCurrent();
            switch ($event->event_type) {
                case ProjectionEventTypes::ProjectionUpdated:
                    $data = \json_decode($event->data, true);

                    if (0 !== \json_last_error()) {
                        throw new Error('Could not json decode event data for projection');
                    }

                    $handlerType = $data['handlerType'];

                    if ($handlerType !== 'PHP') {
                        throw new Error('Unexpected handler type "' . $handlerType . '" given');
                    }

                    $config = new ProjectionConfig(
                        new Principal($data['runAs']['name'], ['$all']), // @todo load roles
                        $data['mode'] !== 'Continuous',
                        $data['emitEnabled'],
                        $data['checkpointsEnabled'],
                        $data['trackEmittedStreams'],
                        \max($data['checkpointAfterMs'] ?? 0, self::MinCheckpointAfterMs),
                        $data['checkpointHandledThreshold'] ?? self::DefaultCheckpointHandledThreshold,
                        $data['checkpointUnhandledBytesThreshold'] ?? self::DefaultCheckpointUnhandledBytesThreshold,
                        $data['pendingEventsThreshold'] ?? self::DefaultPendingEventsThreshold,
                        \min($data['maxWriteBatchLength'], self::MaxMaxWriteBatchLength),
                        null
                    );

                    $query = $data['query'];
                    $mode = ProjectionMode::byName($data['mode']);
                    $enabled = $data['enabled'] ?? false;
                    $projectionEventNumber = $event->event_number;
                    break;
                case '$stop':
                    $enabled = false;
                    $projectionEventNumber = $event->event_number;
                    break;
                case '$start':
                    $enabled = true;
                    $projectionEventNumber = $event->event_number;
                    break;
            }
        }

        return new LoadConfigResult($query, $mode, $enabled, $projectionEventNumber);
    }
}
