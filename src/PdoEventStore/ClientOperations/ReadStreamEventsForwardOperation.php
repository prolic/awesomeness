<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\Messages\EventRecord;
use Prooph\EventStore\Messages\ResolvedIndexedEvent;
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
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ): StreamEventsSlice {
        if ($resolveLinkTos) {
            $sql = <<<SQL
SELECT
    e1.event_id as event_id,
    e1.stream_name as stream_name,
    e2.stream_name as link_stream_name,
    e1.event_number as event_number,
    e2.event_number as link_event_number,
    e1.event_type as event_type,
    e2.event_type as link_event_type,
    e1.data as data,
    e2.data as link_data,
    e1.meta_data as meta_data,
    e2.meta_data as link_meta_data,
    e1.is_json as is_json,
    e2.is_json as link_is_json,
    e1.updated as updated,
    e2.updated as link_updated
FROM
    events e1
LEFT JOIN events e2
    ON (e1.link_to_stream_name = e2.stream_name AND e1.link_to_event_number = e2.event_number)
WHERE e1.stream_name = ?
AND e1.event_number >= ?
ORDER BY e1.event_number ASC
LIMIT ?
SQL;
        } else {
            $sql = <<<SQL
SELECT
    e1.event_id as event_id,
    e1.stream_name as stream_name,
    e1.event_number as event_number,
    e1.event_type as event_type,
    e1.data as data,
    e1.meta_data as meta_data,
    e1.is_json as is_json,
    e1.updated as updated,
FROM
    events e1
WHERE e1.stream_name = ?
AND e1.event_number >= ?
ORDER BY e1.event_number ASC
LIMIT ?
SQL;
        }

        $statement = $connection->prepare($sql);
        $statement->setFetchMode(PDO::FETCH_OBJ);
        $statement->execute([$stream, $start, $count]);

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

        while ($data = $statement->fetch()) {
            $eventRecord = new EventRecord(
                $data->stream_name,
                $data->event_number,
                EventId::fromString($data->event_id),
                $data->event_type,
                $data->is_json,
                $data->data,
                $data->metadata,
                DateTimeUtil::create($data->updated)
            );

            $link = null;

            if ($resolveLinkTos && null === $data->link_event_number) {
                $link = new EventRecord(
                    $data->link_stream_name,
                    $data->link_event_number,
                    EventId::fromString($data->event_id),
                    $data->link_event_type,
                    $data->link_is_json,
                    $data->link_data,
                    $data->link_metadata,
                    DateTimeUtil::create($data->link_updated)
                );
            }

            $events[] = new ResolvedIndexedEvent($eventRecord, $link);

            $lastEventNumber = $data->event_number;
        }

        return new StreamEventsSlice(
            SliceReadStatus::success(),
            $stream,
            $start,
            ReadDirection::forward(),
            $events,
            $lastEventNumber + 1,
            $lastEventNumber,
            false
        );
    }
}
