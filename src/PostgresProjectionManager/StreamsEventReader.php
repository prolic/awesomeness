<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager;

use Amp\Delayed;
use Amp\Postgres\Pool;
use Amp\Postgres\ResultSet;
use Amp\Postgres\Statement;
use Amp\Sync\LocalMutex;
use Generator;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\RecordedEvent;
use SplQueue;
use Throwable;

/** @internal */
class StreamsEventReader extends EventReader
{
    /** @var string[] */
    private $streamNames;
    /** @var int */
    private $fromSequenceNumber;
    /** @var string */
    private $rowPlaces;

    public function __construct(
        LocalMutex $readMutex,
        Pool $pool,
        SplQueue $queue,
        bool $stopOnEof,
        array $streamNames,
        int $fromSequenceNumber
    ) {
        parent::__construct($readMutex, $pool, $queue, $stopOnEof);

        $this->streamNames = $streamNames;
        $this->fromSequenceNumber = $fromSequenceNumber;
        $this->rowPlaces = implode(', ', array_fill(0, count($this->streamNames), '?'));
    }

    /** @throws Throwable */
    protected function doRequestEvents(): Generator
    {
        $sql = <<<SQL
SELECT
    COALESCE(e2.event_id, e2.event_id) as event_id,
    COALESCE(e2.stream_name, e1.stream_name) as stream_name,
    COALESCE(e2.event_number, e1.event_number) as event_number,
    COALESCE(e2.event_type, e1.event_type) as event_type,
    COALESCE(e2.data, e1.data) as data,
    COALESCE(e2.meta_data, e1.meta_data) as meta_data,
    COALESCE(e2.is_json, e1.is_json) as is_json,
    COALESCE(e2.updated, e1.updated) as updated
FROM
    events e1
LEFT JOIN events e2
    ON (e1.link_to_stream_name = e2.stream_name AND e1.link_to_event_number = e2.event_number)
WHERE e1.stream_name IN ({$this->rowPlaces})
AND e1.event_number > ?
ORDER BY e1.event_number ASC
LIMIT ?
SQL;
        $params = $this->streamNames;
        $params[] = $this->fromSequenceNumber;
        $params[] = self::MaxReads;

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare($sql);
        /** @var ResultSet $result */
        $result = yield $statement->execute($params);

        $readEvents = 0;

        while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
            $row = $result->getCurrent();
            ++$readEvents;

            $this->queue->enqueue(new RecordedEvent(
                $row->stream_name,
                EventId::fromString($row->event_id),
                $row->event_number,
                $row->event_type,
                $row->data,
                $row->meta_data,
                $row->is_json,
                DateTimeUtil::create($row->updated)
            ));
        }

        $this->fromSequenceNumber += $readEvents;

        if (0 === $readEvents && $this->stopOnEof) {
            $this->eof = true;
        }

        if (0 === $readEvents) {
            yield new Delayed(200);
        }
    }

    public function head(): Generator
    {
        $sql = <<<SQL
SELECT MAX(event_number) as head, stream_name FROM events WHERE stream_name IN ($this->rowPlaces)
GROUP BY stream_name
SQL;

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare($sql);
        /** @var ResultSet $result */
        $result = yield $statement->execute([$this->streamName]);

        $data = [];

        while(yield $result->advance(ResultSet::FETCH_OBJECT)) {
            $row = $result->getCurrent();
            $data[$row->stream_name] = $row->head;
        }

        return $data;
    }
}