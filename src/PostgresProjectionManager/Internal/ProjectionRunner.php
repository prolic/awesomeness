<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Internal;

use Amp\Delayed;
use Amp\Loop;
use Amp\Postgres\CommandResult;
use Amp\Postgres\Connection;
use Amp\Postgres\Pool;
use Amp\Postgres\ResultSet;
use Amp\Postgres\Statement;
use Amp\Promise;
use Amp\Success;
use cash\LRUCache;
use DateTimeImmutable;
use DateTimeZone;
use Error;
use Generator;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\Projections\ProjectionEventTypes;
use Prooph\EventStore\Projections\ProjectionNames;
use Prooph\EventStore\Projections\ProjectionState;
use Prooph\EventStore\RecordedEvent;
use Prooph\PdoEventStore\Internal\StreamOperation;
use Prooph\PostgresProjectionManager\Internal\Exception\StreamNotFound;
use Psr\Log\LoggerInterface as PsrLogger;
use SplQueue;
use Throwable;
use function Amp\call;
use function array_intersect;
use function current;
use function explode;
use function floor;
use function in_array;
use function is_string;
use function microtime;

/**
 * @internal
 */
class ProjectionRunner
{
    private const CheckedStreamsCacheSize = 10000;

    /** @var SplQueue */
    private $queue;
    /** @var Pool */
    private $pool;
    /** @var Connection */
    private $lockConnection;
    /** @var string */
    private $name;
    /** @var string */
    private $id;
    /** @var PsrLogger */
    private $logger;
    /** @var ProjectionState */
    private $state;
    /** @var string */
    private $handlerType;
    /** @var string */
    private $query;
    /** @var string */
    private $mode;
    /** @var bool */
    private $enabled;
    /** @var bool */
    private $emitEnabled;
    /** @var bool */
    private $checkpointsDisabled;
    /** @var bool */
    private $trackEmittedStreams;
    /** @var int */
    private $checkpointAfterMs = 1;
    /** @var int */
    private $checkpointHandledThreshold = 4000;
    /** @var int */
    private $checkpointUnhandledBytesThreshold = 10000000;
    /** @var int */
    private $pendingEventsThreshold = 50;
    /** @var array */
    private $runAs;
    /** @var array */
    private $streamPositions = [];
    /** @var int */
    private $lastCheckPointMs;
    /** @var LRUCache */
    private $checkedStreams;
    /** @var array */
    private $toWriteStreams = [];
    /** @var array */
    private $toWrite = [];
    /** @var int */
    private $currentBatchSize = 0;

    public function __construct(
        Pool $pool,
        string $name,
        string $id,
        PsrLogger $logger
    ) {
        $this->pool = $pool;
        $this->name = $name;
        $this->id = $id;
        $this->logger = $logger;
        $this->state = ProjectionState::stopped();
        $this->checkedStreams = new LRUCache(self::CheckedStreamsCacheSize);
        $this->queue = new SplQueue();
    }

    /** @throws Throwable */
    public function bootstrap(): Promise
    {
        return call(function (): Generator {
            yield from $this->load();

            if ($this->enabled) {
                yield $this->start();
            }
        });
    }

    /** @throws Throwable */
    private function load(): Generator
    {
        $this->logger->debug('Loading projection ' . $this->name);

        $this->state = ProjectionState::initial();

        $this->lockConnection = yield $this->pool->extractConnection();

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare(<<<SQL
SELECT streams.mark_deleted, streams.deleted, STRING_AGG(stream_acl.role, ',') as stream_roles
    FROM streams
    LEFT JOIN stream_acl ON streams.stream_name = stream_acl.stream_name AND stream_acl.operation = ?
    WHERE streams.stream_name = ?
    GROUP BY streams.stream_name, streams.mark_deleted, streams.deleted
    LIMIT 1;
SQL
        );

        /** @var ResultSet $result */
        $result = yield $statement->execute([
            StreamOperation::Read,
            ProjectionNames::ProjectionsStreamPrefix . $this->name,
        ]);

        if (! yield $result->advance(ResultSet::FETCH_OBJECT)) {
            throw new Error('Stream for projection "' . $this->name .'" not found');
        }

        $data = $result->getCurrent();

        if ($data->mark_deleted || $data->deleted) {
            throw Exception\StreamDeleted::with(ProjectionNames::ProjectionsStreamPrefix . $this->name);
        }

        $toCheck = [SystemRoles::All];
        if (is_string($data->stream_roles)) {
            $toCheck = explode(',', $data->stream_roles);
        }

        $sql = <<<SQL
SELECT
    e2.event_id as event_id,
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
ORDER BY e1.event_number ASC
SQL;
        /** @var Statement $statement */
        $statement = yield $this->pool->prepare($sql);

        /** @var ResultSet $result */
        $result = yield $statement->execute([ProjectionNames::ProjectionsStreamPrefix . $this->name]);

        while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
            $event = $result->getCurrent();
            switch ($event->event_type) {
                case ProjectionEventTypes::ProjectionUpdated:
                    $data = json_decode($event->data, true);
                    if (0 !== json_last_error()) {
                        throw new Error('Could not json decode event data for projection "' . $this->name . '"');
                    }

                    $this->handlerType = $data['handlerType'];
                    $this->query = $data['query'];
                    $this->mode = $data['mode'];
                    $this->enabled = $data['enabled'] ?? false;
                    $this->emitEnabled = $data['emitEnabled'];
                    $this->checkpointsDisabled = $data['checkpointsDisabled'];
                    $this->trackEmittedStreams = $data['trackEmittedStreams'];
                    if (isset($data['checkpointAfterMs'])) {
                        $this->checkpointAfterMs = $data['checkpointAfterMs'];

                        if ($this->checkpointAfterMs < 1) {
                            $this->checkpointAfterMs = 1;
                        }
                    }
                    if (isset($data['checkpointHandledThreshold'])) {
                        $this->checkpointHandledThreshold = $data['checkpointHandledThreshold'];
                    }
                    if (isset($data['checkpointUnhandledBytesThreshold'])) {
                        $this->checkpointUnhandledBytesThreshold = $data['checkpointUnhandledBytesThreshold'];
                    }
                    if (isset($data['pendingEventsThreshold'])) {
                        $this->pendingEventsThreshold = $data['pendingEventsThreshold'];
                    }
                    $this->runAs = $data['runAs'];
                    $this->runAs['roles'][] = '$all';
                    break;
                case '$stopped':
                    $this->enabled = false;
                    break;
                case '$started':
                    $this->enabled = true;
                    break;
            }
        }

        if ('$system' !== $this->runAs['name']
            && ! in_array($this->runAs['name'], $toCheck)
            && (! isset($this->runAs['roles']) || empty(array_intersect($this->runAs['roles'], $toCheck)))
        ) {
            throw Exception\AccessDenied::toStream(ProjectionNames::ProjectionsStreamPrefix . $this->name);
        }

        $checkpointStream = ProjectionNames::ProjectionsStreamPrefix . $this->name . ProjectionNames::ProjectionCheckpointStreamSuffix;

        try {
            $sql = <<<SQL
SELECT
    e2.event_id as event_id,
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
ORDER BY e1.event_number DESC
LIMIT 1;
SQL;
            /** @var Statement $statement */
            $statement = yield $this->pool->prepare($sql);

            /** @var ResultSet $result */
            $result = yield $statement->execute([$checkpointStream]);

            if (yield $result->advance(ResultSet::FETCH_OBJECT)) {
                $data = $result->getCurrent();
                $streamPositions = json_decode($data->data, true);

                if (0 !== json_last_error()) {
                    throw new Exception\RuntimeException('Could not json decode checkpoint for ' . $this->name);
                }

                $this->streamPositions = $streamPositions['$s'];
            }
        } catch (StreamNotFound $e) {
            // ignore, no checkpoint found
        }
    }

    /** @throws Throwable */
    public function start(): Promise
    {
        if (! $this->state->equals(ProjectionState::initial())) {
            throw new Error('Cannot start projection "' . $this->name . '": already ' . $this->state->name());
        }

        return call(function (): Generator {
            $this->enabled = true;
            $this->lastCheckPointMs = microtime(true) * 10000;

            yield from $this->runQuery();
        });
    }

    public function disable(): void
    {
        if (! $this->state->equals(ProjectionState::running())) {
            return;
        }

        $this->enabled = false;
        $this->state = ProjectionState::stopping();
    }

    public function shutdown(): void
    {
        if ($this->state->equals(ProjectionState::stopped())) {
            $this->logger->info('shutdown done');
            return;
        }

        if ($this->state->equals(ProjectionState::initial())) {
            $this->state = ProjectionState::stopped();
            $this->logger->info('shutdown done');
            return;
        }

        $this->state = ProjectionState::stopping();

        Loop::repeat(100, function (string $watcherId) {
            if ($this->state->equals(ProjectionState::stopped())) {
                Loop::cancel($watcherId);
                $this->logger->info('shutdown done');
            } else {
                $this->logger->debug('still waiting for shutdown...');
            }
        });
    }

    /** @throws Throwable */
    private function write(): Generator
    {
        if (0 === count($this->toWrite)) {
            if ($this->state->equals(ProjectionState::running())) {
                Loop::defer(function (): Generator {
                    yield from $this->write();
                });
            }

            return null;
        }

        $streams = $this->toWriteStreams;
        $data = $this->toWrite;
        $expectedVersions = [];

        $this->toWriteStreams = [];
        $this->toWrite = [];

        foreach ($streams as $stream) {
            yield from $this->acquireLock($this->lockConnection, $stream);
        }

        foreach ($streams as $stream) {
            $expectedVersion = ExpectedVersion::NoStream;

            try {
                yield $this->checkStream($stream);
                $expectedVersion = yield from $this->getExpectedVersion($stream);
            } catch (StreamNotFound $e) {
                yield from $this->createStream($stream);
            }

            $expectedVersions[$stream] = $expectedVersion;
        }

        $sql = <<<SQL
INSERT INTO events (stream_name, event_id, event_type, data, meta_data, is_json, updated, link_to_stream_name, link_to_event_number, event_number)
VALUES 
SQL;
        $sql .= str_repeat('(?, ?, ?, ?, ?, ?, ?, ?, ?, ?), ', count($data));
        $sql = substr($sql, 0, -2) . ';';

        $params = [];
        foreach ($data as $record) {
            foreach ($record as $row) {
                $params[] = $row;
            }
            $params[] = ++$expectedVersions[$record['stream']];
        }

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare($sql);
        /** @var CommandResult $result */
        $result = yield $statement->execute($params);

        if (0 === $result->affectedRows()) {
            throw new Exception\RuntimeException('Could not write events');
        }

        foreach ($streams as $stream) {
            yield from $this->releaseLock($this->lockConnection, $stream);
        }

        if ($this->state->equals(ProjectionState::running())) {
            Loop::defer(function (): Generator {
                yield from $this->write();
            });
        }
    }

    /** @throws Throwable */
    private function runQuery(): Generator
    {
        $this->logger->debug('Running projection ' . $this->name);

        $notify = function (string $streamName, string $eventType, string $data, string $metadata = '', bool $isJson = false): void {
            $this->emit($streamName, $eventType, $data, $metadata, $isJson);
        };

        $evaluator = new ProjectionEvaluator($notify);
        $processor = $evaluator->evaluate($this->query);
        $processor->initState();

        $this->state = ProjectionState::running();

        /** @var EventReader $reader */
        $reader = yield $this->determineReader($processor);
        $reader->run();

        Loop::delay(100, function (): Generator { // write up to 10 times per second
            yield from $this->write();
        });

        Loop::delay($this->checkpointAfterMs, function (): Generator {
            yield from $this->writeCheckPoint();
        });

        while (! $reader->eof()) {
            if (! $this->state->equals(ProjectionState::running())) {
                $reader->pause();
                break;
            }

            if ($this->queue->isEmpty()) {
                yield new Delayed(200);
                continue;
            }

            /** @var RecordedEvent $event */
            $event = $this->queue->dequeue();
            $processor->processEvent($event);
            $this->streamPositions[$event->streamId()] = $event->eventNumber();
            ++$this->currentBatchSize;

            if ($this->queue->count() > $this->pendingEventsThreshold) {
                $reader->pause();
            }

            if ($this->queue->count() < $this->pendingEventsThreshold
                && $reader->paused()
            ) {
                $reader->run();
            }

            yield new Delayed(0); // let some other work be done, too
        }

        while (! $this->queue->isEmpty()) {
            /** @var RecordedEvent $event */
            $event = $this->queue->dequeue();
            $processor->processEvent($event);
            $this->streamPositions[$event->streamId()] = $event->eventNumber();
            ++$this->currentBatchSize;

            yield new Delayed(0); // let some other work be done, too
        }

        if ($this->currentBatchSize > 0) {
            yield from $this->writeCheckPoint();
        }

        $this->state = ProjectionState::stopped();
    }

    /** @throws Throwable */
    private function writeCheckPoint(): Generator
    {
        if ($this->state->equals(ProjectionState::stopping())
            || $this->currentBatchSize < $this->checkpointHandledThreshold
            || (
                0 !== $this->checkpointAfterMs
                || (floor(microtime(true) * 10000 - $this->lastCheckPointMs) < $this->checkpointAfterMs)
            )
        ) {
            if ($this->state->equals(ProjectionState::running())) {
                Loop::delay($this->checkpointAfterMs, function (): Generator {
                    yield from $this->writeCheckPoint();
                });
            }

            return null;
        }

        $this->logger->info('writing checkpoint');

        $checkpointStream = ProjectionNames::ProjectionsStreamPrefix . $this->name . ProjectionNames::ProjectionCheckpointStreamSuffix;
        $batchSize = $this->currentBatchSize;
        $streamPositions = $this->streamPositions;

        try {
            yield $this->checkStream($checkpointStream);
        } catch (StreamNotFound $e) {
            yield from $this->createStream($checkpointStream);
        }

        yield from $this->acquireLock($this->lockConnection, $checkpointStream);
        $expectedVersion = yield from $this->getExpectedVersion($checkpointStream);

        $sql = <<<SQL
INSERT INTO events (event_id, event_number, event_type, data, meta_data, stream_name, is_json, updated) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?);
SQL;
        $now = new DateTimeImmutable('NOW', new DateTimeZone('UTC'));

        $params[] = EventId::generate()->toString();
        $params[] = ++$expectedVersion;
        $params[] = ProjectionEventTypes::ProjectionCheckpoint;
        $params[] = json_encode([
            '$s' => $streamPositions,
        ]);
        $params[] = '';
        $params[] = $checkpointStream;
        $params[] = true;
        $params[] = DateTimeUtil::format($now);

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare($sql);
        /** @var CommandResult $result */
        $result = yield $statement->execute($params);

        if (0 === $result->affectedRows()) {
            throw new Exception\RuntimeException('Could not write checkpoint for ' . $this->name);
        }

        yield from $this->releaseLock($this->lockConnection, $checkpointStream);

        $this->currentBatchSize = $this->currentBatchSize - $batchSize;
        $this->lastCheckPointMs = microtime(true) * 10000;

        $this->logger->info('Checkpoint created');

        if ($this->state->equals(ProjectionState::running())) {
            Loop::delay($this->checkpointAfterMs, function (): Generator {
                yield from $this->writeCheckPoint();
            });
        }
    }

    /** @throws Throwable */
    private function determineReader(EventProcessor $processor): Promise
    {
        return call(function () use ($processor): Generator {
            if (count($processor->sources()['streams']) === 1) {
                $streamName = current($processor->sources()['streams']);

                if (! isset($this->streamPositions[$streamName])) {
                    $this->streamPositions[$streamName] = -1;
                }

                yield $this->checkStream($streamName);

                return yield new Success(new StreamEventReader(
                    $this->pool,
                    $this->queue,
                    'Continuous' !== $this->mode,
                    $streamName,
                    $this->streamPositions[$streamName]
                ));
            }

            // @todo implement
            throw new Exception\RuntimeException('Not implemented');
        });
    }

    /** @throws Throwable */
    private function checkStream(string $streamName): Promise
    {
        $result = $this->checkedStreams->get($streamName);

        if (null !== $result) {
            return new Success();
        }

        return call(function () use ($streamName): Generator {
            /** @var Statement $statement */
            $statement = yield $this->pool->prepare(<<<SQL
SELECT streams.mark_deleted, streams.deleted, STRING_AGG(stream_acl.role, ',') as stream_roles
FROM streams
LEFT JOIN stream_acl ON streams.stream_name = stream_acl.stream_name AND stream_acl.operation = ?
WHERE streams.stream_name = ?
GROUP BY streams.stream_name, streams.mark_deleted, streams.deleted
LIMIT 1;
SQL
            );

            /** @var ResultSet $result */
            $result = yield $statement->execute([StreamOperation::Read, $streamName]);

            while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
                $data = $result->getCurrent();

                if ($data->mark_deleted || $data->deleted) {
                    throw Exception\StreamDeleted::with($streamName);
                }

                $toCheck = [SystemRoles::All];

                if (is_string($data->stream_roles)) {
                    $toCheck = explode(',', $data->stream_roles);
                }

                if ('$system' !== $this->runAs['name']
                    && ! in_array($this->runAs['name'], $toCheck)
                    && (! isset($this->runAs['roles']) || empty(array_intersect($this->runAs['roles'], $toCheck)))
                ) {
                    throw Exception\AccessDenied::toStream($streamName);
                }

                $this->checkedStreams->put($streamName, 'ok');

                return null;
            }

            throw StreamNotFound::with($streamName);
        });
    }

    /** @throws Throwable */
    private function acquireLock(Connection $connection, string $name): Generator
    {
        /** @var Statement $statement */
        $statement = yield $connection->prepare('SELECT PG_ADVISORY_LOCK(HASHTEXT(?)) as stream_lock;');
        /** @var ResultSet $result */
        $result = yield $statement->execute([$name]);
        yield $result->advance(ResultSet::FETCH_OBJECT);
    }

    /** @throws Throwable */
    private function releaseLock(Connection $connection, string $name): Generator
    {
        /** @var Statement $statement */
        $statement = yield $connection->prepare('SELECT PG_ADVISORY_UNLOCK(HASHTEXT(?)) as stream_lock;');
        /** @var ResultSet $result */
        $result = yield $statement->execute([$name]);
        yield $result->advance(ResultSet::FETCH_OBJECT);
        $lock = $result->getCurrent()->stream_lock;

        if (! $lock) {
            throw new Exception\RuntimeException('Could not release lock for ' . $name);
        }
    }

    public function emit(string $stream, string $eventType, string $data, string $metadata, bool $isJson = false): void
    {
        if (! in_array($stream, $this->toWriteStreams, true)) {
            $this->toWriteStreams[] = $stream;
        }

        $now = new DateTimeImmutable('NOW', new DateTimeZone('UTC'));

        $eventData = [
            'stream' => $stream,
            'eventId' => EventId::generate()->toString(),
            'eventType' => $eventType,
            'data' => $data,
            'metadata' => $metadata,
            'isJson' => $isJson,
            'updated' => DateTimeUtil::format($now),
            'link_to_stream_name' => null,
            'link_to_event_number' => null,
        ];

        if ($eventType === SystemEventTypes::LinkTo) {
            $linkTo = explode('@', $data);

            $eventData['link_to_stream_name'] = $linkTo[1];
            $eventData['link_to_event_number'] = $linkTo[0];
        }

        $this->toWrite[] = $eventData;
    }

    /*
        public function reset(): void
        {
            $this->streamPositions = [];
            $callback = $this->initCallback;
            $this->state = [];

            if (is_callable($callback)) {
                $result = $callback();
                if (is_array($result)) {
                    $this->state = $result;
                }
            }

            $projectionsTable = $this->quoteTableName($this->projectionsTable);

            $sql = <<<EOT
    UPDATE $projectionsTable SET position = ?, state = ?, status = ?
    WHERE name = ?
    EOT;
            $statement = $this->connection->prepare($sql);
            try {
                $statement->execute([
                    json_encode($this->streamPositions),
                    json_encode($this->state),
                    $this->status->getValue(),
                    $this->name,
                ]);
            } catch (PDOException $exception) {
                // ignore and check error code
            }
            if ($statement->errorCode() !== '00000') {
                throw RuntimeException::fromStatementErrorInfo($statement->errorInfo());
            }
            try {
                $this->eventStoreConnection->delete(new StreamName($this->name));
            } catch (Exception\StreamNotFound $exception) {
                // ignore
            }
        }
    */

    /*
        public function getState(): array
        {
            return $this->state;
        }

        public function delete(bool $deleteEmittedEvents): void
        {
            $projectionsTable = $this->quoteTableName($this->projectionsTable);
            $deleteProjectionSql = <<<EOT
    DELETE FROM $projectionsTable WHERE name = ?;
    EOT;
            $statement = $this->connection->prepare($deleteProjectionSql);
            try {
                $statement->execute([$this->name]);
            } catch (PDOException $exception) {
                // ignore and check error code
            }
            if ($statement->errorCode() !== '00000') {
                throw RuntimeException::fromStatementErrorInfo($statement->errorInfo());
            }
            if ($deleteEmittedEvents) {
                try {
                    $this->eventStoreConnection->delete(new StreamName($this->name));
                } catch (Exception\StreamNotFound $e) {
                    // ignore
                }
            }
            $this->isStopped = true;
            $callback = $this->initCallback;
            $this->state = [];
            if (is_callable($callback)) {
                $result = $callback();
                if (is_array($result)) {
                    $this->state = $result;
                }
            }
            $this->streamPositions = [];
        }
    */

    /** @throws Throwable */
    private function createStream(string $streamName): Generator
    {
        /** @var Statement $statement */
        $statement = yield $this->pool->prepare(<<<SQL
INSERT INTO streams (stream_name, mark_deleted, deleted) VALUES (?, ?, ?);
SQL
        );
        /** @var CommandResult $result */
        $result = yield $statement->execute([$streamName, 0, 0]);

        if (0 === $result->affectedRows()) {
            throw new Exception\RuntimeException('Could not create stream for ' . $streamName);
        }
    }

    /** @throws Throwable */
    private function getExpectedVersion(string $streamName): Generator
    {
        $expectedVersion = ExpectedVersion::NoStream;

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare(<<<SQL
SELECT MAX(event_number) as current_version FROM events WHERE stream_name = ?
SQL
            );
        /** @var ResultSet $result */
        $result = yield $statement->execute([$streamName]);

        while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
            $expectedVersion = $result->getCurrent()->current_version;
        }

        if (null === $expectedVersion) {
            $expectedVersion = ExpectedVersion::EmptyStream;
        }

        return $expectedVersion;
    }
}
