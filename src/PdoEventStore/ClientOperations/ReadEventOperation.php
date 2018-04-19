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
        ?string $streamId,
        int $eventNumber,
        ?UserCredentials $userCredentials
    ): EventReadResult {
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
AND e1.event_number = ?
SQL
        );
        $statement->setFetchMode(PDO::FETCH_OBJ);
        $statement->execute([$streamId, $eventNumber]);

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
                EventId::fromString($event->event_id),
                $eventNumber,
                $event->event_type,
                $event->data,
                $event->meta_data,
                $event->is_json,
                DateTimeUtil::create($event->updated)
            )
        );
    }
}
