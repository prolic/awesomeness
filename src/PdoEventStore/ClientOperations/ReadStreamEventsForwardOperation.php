<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\ReadDirection;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\UserCredentials;

/** @internal */
class ReadStreamEventsForwardOperation
{
    public function __invoke(
        PDO $connection,
        string $stream,
        int $start,
        int $count,
        ?UserCredentials $userCredentials
    ): StreamEventsSlice
    {
        $statement = $connection->prepare(<<<SQL
SELECT * FROM streams WHERE stream_name = ?
SQL
        );
        $statement->execute([$stream]);
        $statement->setFetchMode(PDO::FETCH_OBJ);

        if (0 === $statement->rowCount()) {
            return new StreamEventsSlice(
                SliceReadStatus::streamNotFound(),
                $stream,
                $start,
                ReadDirection::forward(),
                [],
                0,
                0,
                true
            );
        }

        $streamData = $statement->fetch();

        if ($streamData->mark_deleted || $streamData->deleted) {
            return new StreamEventsSlice(
                SliceReadStatus::streamDeleted(),
                $stream,
                $start,
                ReadDirection::forward(),
                [],
                0,
                0,
                true
            );
        }

        $statement = $connection->prepare(<<<SQL
SELECT
    COALESCE(e1.event_id, e2.event_id) as event_id,
    e1.event_number as event_number,
    COALESCE(e1.event_type, e2.event_type) as event_type,
    COALESCE(e1.data, e2.data) as data,
    COALESCE(e1.meta_data, e2.meta_data) as meta_data,
    COALESCE(e1.is_json, e2.is_json) as is_json,
    COALESCE(e1.is_meta_data, e2.is_meta_data) as is_meta_data,
    COALESCE(e1.updated, e2.updated) as updated
FROM
    events e1
LEFT JOIN events e2
    ON (e1.link_to = e2.event_id)
WHERE e1.stream_id = ?
AND e1.event_number >= ?
ORDER BY e1.event_number ASC
LIMIT ?
SQL
        );
        $statement->setFetchMode(PDO::FETCH_OBJ);
        $statement->execute([$streamData->stream_id, $start, $count]);

        if (0 === $statement->rowCount()) {
            return new StreamEventsSlice(
                SliceReadStatus::success(),
                $stream,
                $start,
                ReadDirection::forward(),
                [],
                0,
                0,
                true
            );
        }

        $events = [];
        $lastEventNumber = 0;

        while ($event = $statement->fetch()) {
            $events[] = new RecordedEvent(
                $stream,
                EventId::fromString($event->event_id),
                $event->event_number,
                $event->event_type,
                $event->data,
                $event->meta_data,
                $event->is_json,
                DateTimeUtil::create($event->updated)
            );

            $lastEventNumber = $event->event_number;
        }

        return new StreamEventsSlice(
            SliceReadStatus::success(),
            $stream,
            $start,
            ReadDirection::forward(),
            [],
            $lastEventNumber + 1,
            $lastEventNumber,
            true
        );
    }
}
