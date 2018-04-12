<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventReadStatus;

/** @internal */
class ReadEventOperation
{
    public function __invoke(PDO $connection, string $stream, int $eventNumber): EventReadResult
    {
        $statement = $connection->prepare(
            'SELECT * FROM events WHERE streamId = ? AND eventNumber = ? LIMIT 1'
        );
        $statement->setFetchMode(PDO::FETCH_OBJ);
        $statement->execute([$stream, $eventNumber]);

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
                DateTimeFactory::create($event->updated)
            )
        );
    }
}
