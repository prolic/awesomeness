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

/** @internal */
class ReadStreamEventsBackwardOperation
{
    public function __invoke(PDO $connection, string $stream, int $start, int $count): StreamEventsSlice
    {
        $statement = $connection->prepare(<<<SQL
SELECT * FROM streams WHERE streamName = ?
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

        if ($streamData->markDeleted || $streamData->deleted) {
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
    COALESCE(e1.eventId, e2.eventId) as eventId,
    e1.eventNumber as eventNumber,
    COALESCE(e1.eventType, e2.eventType) as eventType,
    COALESCE(e1.data, e2.data) as data,
    COALESCE(e1.metadata, e2.metadata) as metadata,
    COALESCE(e1.isJson, e2.isJson) as isJson,
    COALESCE(e1.isMetaData, e2.isMetaData) as isMetaData,
    COALESCE(e1.updated, e2.updated) as updated
FROM
    events e1
LEFT JOIN events e2
    ON (e1.linkTo = e2.eventId)
WHERE e1.streamId = ?
AND e1.eventNumber >= ?
ORDER BY e1.eventNumber DESC
LIMIT ?
SQL
        );
        $statement->setFetchMode(PDO::FETCH_OBJ);
        $statement->execute([$streamData->streamId, $start, $count]);

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
                EventId::fromString($event->eventId),
                $event->eventNumber,
                $event->eventType,
                $event->data,
                $event->metaData,
                $event->isJson,
                DateTimeUtil::create($event->updated)
            );

            $lastEventNumber = $event->eventNumber;
        }

        return new StreamEventsSlice(
            SliceReadStatus::success(),
            $stream,
            $start,
            ReadDirection::backward(),
            [],
            $lastEventNumber + 1,
            $lastEventNumber,
            true
        );
    }
}
