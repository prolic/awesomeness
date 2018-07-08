<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Processing;

use Amp\Delayed;
use Amp\Postgres\ResultSet;
use Amp\Postgres\Statement;
use Generator;
use Prooph\EventStore\Data\EventId;
use Prooph\EventStore\Internal\DateTimeUtil;
use Throwable;

/** @internal */
class AllStreamEventReader extends EventReader
{
    /** @throws Throwable */
    protected function doRequestEvents(): Generator
    {
        $sql = <<<SQL
SELECT
    e1.prepare_position,
    e1.commit_position,
    COALESCE(e2.event_id, e1.event_id) as event_id,
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
WHERE e1.prepare_position > ?
ORDER BY e1.prepare_position ASC
LIMIT ?
SQL;

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare($sql);

        /** @var ResultSet $result */
        $result = yield $statement->execute([$this->checkpointTag->preparePosition(), self::MaxReads]);

        $readEvents = 0;

        while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
            $row = $result->getCurrent();
            ++$readEvents;

            $this->queue->enqueue(new ResolvedEvent(
                $row->stream_name,
                EventId::fromString($row->event_id),
                $row->event_number,
                $row->event_type,
                $row->data,
                $row->meta_data,
                $row->is_json,
                DateTimeUtil::create($row->updated),
                '' // @todo fetch stream metadata
            ));

            $this->checkpointTag->updatePosition($row->prepare_position, $row->commit_position);
        }

        if (0 === $readEvents && $this->stopOnEof) {
            $this->eof = true;
        }

        if (0 === $readEvents) {
            yield new Delayed(200);
        }
    }

    public function progress(): Generator
    {
        if ($this->checkpointTag->preparePosition() === -1) {
            return 0.0;
        }

        $sql = 'SELECT MAX(prepare_position) as head FROM events;';

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare($sql);
        /** @var ResultSet $result */
        $result = yield $statement->execute([$this->streamName]);

        yield $result->advance(ResultSet::FETCH_OBJECT);

        $row = $result->getCurrent();

        return \floor($this->checkpointTag->preparePosition() * 100 / $row->head * 10000) / 10000;
    }
}
