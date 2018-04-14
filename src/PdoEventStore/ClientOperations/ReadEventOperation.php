<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\EventId;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventReadStatus;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\UserCredentials;

/** @internal */
class ReadEventOperation
{
    public function __invoke(
        PDO $connection,
        string $stream,
        int $eventNumber,
        ?UserCredentials $userCredentials
    ): EventReadResult
    {
        $statement = $connection->prepare(<<<SQL
SELECT * FROM streams WHERE streamName = ?
SQL
        );
        $statement->execute([$stream]);
        $statement->setFetchMode(PDO::FETCH_OBJ);

        if (0 === $statement->rowCount()) {
            return new EventReadResult(EventReadStatus::noStream(), $stream, $eventNumber, null);
        }

        $streamData = $statement->fetch();

        if ($streamData->markDeleted || $streamData->deleted) {
            return new EventReadResult(EventReadStatus::streamDeleted(), $stream, $eventNumber, null);
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
AND e1.eventNumber = ?
SQL
        );
        $statement->setFetchMode(PDO::FETCH_OBJ);
        $statement->execute([$streamData->streamId, $eventNumber]);

        if (0 === $statement->rowCount()) {
            return new EventReadResult(EventReadStatus::notFound(), $stream, $eventNumber, null);
        }

        $event = $statement->fetch();

        return new EventReadResult(
            EventReadStatus::success(),
            $stream,
            $eventNumber,
            new RecordedEvent(
                $stream,
                EventId::fromString($event->eventId),
                $eventNumber,
                $event->eventType,
                $event->data,
                $event->metaData,
                $event->isJson,
                DateTimeUtil::create($event->updated)
            )
        );
    }
}
