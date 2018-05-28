<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Internal;

use Amp\Postgres\Pool;
use Amp\Postgres\ResultSet;
use Amp\Postgres\Statement;
use Generator;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\RecordedEvent;
use SplQueue;
use Throwable;

/** @internal */
class StreamEventReader extends EventReader
{
    private const MaxReads = 400;

    /** @var string */
    private $streamName;
    /** @var int */
    private $fromSequenceNumber;

    public function __construct(
        Pool $pool,
        SplQueue $queue,
        bool $stopOnEof,
        string $streamName,
        int $fromSequenceNumber
    ) {
        parent::__construct($pool, $queue, $stopOnEof);

        $this->streamName = $streamName;
        $this->fromSequenceNumber = $fromSequenceNumber;
    }

    /** @throws Throwable */
    protected function doRequestEvents(): Generator
    {
        $sql = <<<SQL
SELECT
    COALESCE(e1.event_id, e2.event_id) as event_id,
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
AND e1.event_number >= ?
ORDER BY e1.event_number ASC
LIMIT ?
SQL;

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare($sql);
        /** @var ResultSet $result */
        $result = yield $statement->execute([$this->streamName, $this->fromSequenceNumber, self::MaxReads]);

        $readEvents = 0;

        while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
            $row = $result->getCurrent();
            ++$readEvents;
            $this->queue->enqueue(new RecordedEvent(
                $this->streamName,
                EventId::fromString($row->event_id),
                $row->event_number,
                $row->event_type,
                $row->data,
                $row->meta_data,
                $row->is_json,
                DateTimeUtil::create($row->updated)
            ));

            $this->fromSequenceNumber = $row->event_number + 1;
        }

        if (0 === $readEvents && $this->stopOnEof) {
            $this->eof = true;
        }
    }
}
