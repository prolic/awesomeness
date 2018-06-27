<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\EventId;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventReadStatus;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\Messages\EventRecord;
use Prooph\EventStore\Messages\ResolvedIndexedEvent;
use Prooph\EventStore\UserCredentials;

/** @internal */
class ReadEventOperation
{
    public function __invoke(
        PDO $connection,
        string $stream,
        int $eventNumber,
        bool $resolveLinkTo,
        ?UserCredentials $userCredentials
    ): EventReadResult {
        if ($resolveLinkTo) {
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
SQL;
        }

        if (-1 === $eventNumber) {
            $sql .= <<<SQL
ORDER BY e1.event_number DESC
LIMIT 1
SQL;
            $params = [$stream];
        } else {
            $sql .= ' AND e1.event_number = ?';
            $params = [$stream, $eventNumber];
        }

        $statement = $connection->prepare($sql);
        $statement->setFetchMode(PDO::FETCH_OBJ);
        $statement->execute($params);

        if (0 === $statement->rowCount()) {
            return new EventReadResult(EventReadStatus::notFound(), $stream, $eventNumber, null);
        }

        $data = $statement->fetch();

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

        if ($resolveLinkTo && null === $data->link_event_number) {
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

        return new EventReadResult(
            EventReadStatus::success(),
            $stream,
            $eventNumber,
            new ResolvedIndexedEvent($eventRecord, $link)
        );
    }
}
