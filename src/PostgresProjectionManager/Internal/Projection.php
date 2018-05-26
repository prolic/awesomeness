<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Internal;

use Amp\Coroutine;
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
use Closure;
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
use Ramsey\Uuid\Uuid;
use SplQueue;
use Throwable;
use function Amp\call;
use function array_intersect;
use function current;
use function explode;
use function floor;
use function in_array;
use function is_array;
use function is_callable;
use function is_string;
use function microtime;

/**
 * @internal
 * @see EventStore/src/EventStore.Projections.Core/Prelude/1Prelude.js
 * @see EventStore/src/EventStore.Projections.Core/Prelude/Projections.js
 */
class Projection
{
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
    private $projectionState;
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
    private $checkpointAfterMs = 0;
    /** @var int */
    private $checkpointHandledThreshold = 4000;
    /** @var int */
    private $checkpointUnhandledBytesThreshold = 10000000;
    /** @var int */
    private $pendingEventsThreshold = 50;
    /** @var int */
    private $maxWriteBatchLength = 500;
    /** @var int|null */
    private $maxAllowedWritesInFlight = null;
    /** @var array */
    private $runAs;
    /** @var array */
    private $evaledQuery = [];
    /** @var array */
    private $handlers = [];
    /** @var array */
    private $streamPositions = [];
    /** @var array */
    private $state = [];
    /** @var int */
    private $currentBatchSize = 0;
    /** @var int */
    private $lastCheckPointMs;
    /** @var LRUCache */
    private $knownStreamIds;
    /** @var array */
    private $toWriteStreams = [];
    /** @var array */
    private $toWrite = [];

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
        $this->projectionState = ProjectionState::stopped();
        $this->knownStreamIds = new LRUCache(1000);
        $this->queue = new SplQueue();
    }

    /** @throws Throwable */
    public function bootstrap(): Promise
    {
        return call(function (): Generator {
            yield new Coroutine($this->load());

            if ($this->enabled) {
                yield $this->start();
            }
        });
    }

    /** @throws Throwable */
    private function load(): Generator
    {
        $this->lockConnection = yield $this->pool->extractConnection();

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare(<<<SQL
SELECT streams.stream_id, streams.mark_deleted, streams.deleted, STRING_AGG(stream_acl.role, ',') as stream_roles
    FROM streams
    LEFT JOIN stream_acl ON streams.stream_id = stream_acl.stream_id AND stream_acl.operation = ?
    WHERE streams.stream_name = ?
    GROUP BY streams.stream_id, streams.mark_deleted, streams.deleted
    LIMIT 1;
SQL
        );

        /** @var ResultSet $result */
        $result = yield $statement->execute([
            StreamOperation::Read,
            ProjectionNames::ProjectionsStreamPrefix . $this->name,
        ]);

        if (! yield $result->advance(ResultSet::FETCH_OBJECT)) {
            throw new Error('Stream id for projection "' . $this->name .'" not found');
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
    ON (e1.link_to = e2.event_id)
WHERE e1.stream_id = ?
ORDER BY e1.event_number ASC
SQL;
        /** @var Statement $statement */
        $statement = yield $this->pool->prepare($sql);

        /** @var ResultSet $result */
        $result = yield $statement->execute([$data->stream_id]);

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
                    if (isset($data['maxWriteBatchLength'])) {
                        $this->maxWriteBatchLength = $data['maxWriteBatchLength'];
                    }
                    if (isset($data['maxAllowedWritesInFlight'])) {
                        $this->maxAllowedWritesInFlight = $data['maxAllowedWritesInFlight'];
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
            $streamId = yield $this->determineStreamId($checkpointStream);

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
    ON (e1.link_to = e2.event_id)
WHERE e1.stream_id = ?
ORDER BY e1.event_number DESC
LIMIT 1;
SQL;
            /** @var Statement $statement */
            $statement = yield $this->pool->prepare($sql);

            /** @var ResultSet $result */
            $result = yield $statement->execute([$streamId]);

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
        if (! $this->projectionState->equals(ProjectionState::stopped())) {
            throw new Error('Cannot start projection "' . $this->name . '": already ' . $this->projectionState->name());
        }

        return call(function (): Generator {
            $this->enabled = true;
            $this->projectionState = ProjectionState::initial();

            Loop::repeat(100, function (string $watcherId) {
                if ($this->projectionState->equals(ProjectionState::stopping())) {
                    Loop::cancel($watcherId);
                    $this->projectionState = ProjectionState::stopped();
                }
            });

            $this->lastCheckPointMs = microtime(true) * 10000;

            yield new Coroutine($this->runQuery());
        });

        $this->logger->debug('started');
    }

    public function disable(): void
    {
        if (! $this->projectionState->equals(ProjectionState::running())) {
            return;
        }

        $this->enabled = false;
        $this->projectionState = ProjectionState::stopping();
        $this->logger->debug('disabled');
    }

    public function shutdown(): void
    {
        $this->projectionState = ProjectionState::stopping();
    }

    /** @throws Throwable */
    private function write(): Generator
    {
        if (0 === $this->currentBatchSize) {
            $this->logger->debug('nothing to write');

            return null;
        }

        $streams = $this->toWriteStreams;
        $data = $this->toWrite;
        $streamInfos = [];

        $this->toWriteStreams = [];
        $this->toWrite = [];

        foreach ($streams as $stream) {
            $this->logger->debug('acquire lock for ' . $stream);
            yield new Coroutine($this->acquireLock($this->lockConnection, $stream));
            $this->logger->debug('acquire lock for ' . $stream . ' done');
        }

        foreach ($streams as $stream) {
            $expectedVersion = ExpectedVersion::NoStream;

            try {
                $streamId = yield $this->determineStreamId($stream);

                $this->logger->debug('preparing sql');
                /** @var Statement $statement */
                $statement = yield $this->pool->prepare(<<<SQL
SELECT MAX(event_number) as current_version FROM events WHERE stream_id = ?
SQL
                );
                $this->logger->debug('executing sql');
                /** @var ResultSet $result */
                $result = yield $statement->execute([$streamId]);

                $this->logger->debug('advancing');
                while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
                    $expectedVersion = $result->getCurrent()->current_version;
                }
            } catch (StreamNotFound $e) {
                $streamId = Uuid::uuid4()->toString();
                /** @var Statement $statement */
                $statement = yield $this->pool->prepare(<<<SQL
INSERT INTO streams (stream_id, stream_name, mark_deleted, deleted) VALUES (?, ?, ?, ?);
SQL
                );
                /** @var CommandResult $result */
                $result = yield $statement->execute([$streamId, $stream, 0, 0]);

                if (0 === $result->affectedRows()) {
                    throw new Exception\RuntimeException('Could not create stream id for ' . $stream);
                }
            }

            $streamInfos[$stream] = ['stream_id' => $streamId, 'expected_version' => $expectedVersion];
        }

        $sql = <<<SQL
INSERT INTO events (event_id, event_number, event_type, data, meta_data, stream_id, is_json, updated, link_to)
VALUES 
SQL;
        $sql .= str_repeat('(?, ?, ?, ?, ?, ?, ?, ?, ?), ', count($data));
        $sql = substr($sql, 0, -2) . ';';

        $now = new DateTimeImmutable('NOW', new DateTimeZone('UTC'));
        $now = DateTimeUtil::format($now);

        $params = [];
        foreach ($data as $record) {
            $params[] = EventId::generate();
            $params[] = ++$expectedVersion;
            $params[] = SystemEventTypes::LinkTo;
            $params[] = $streamInfos[$record['stream']]['expected_version'] . '@' . $record['stream'];
            $params[] = $record['metadata'];
            $params[] = $streamInfos[$record['stream']]['stream_id'];
            $params[] = $record['event']->isJson();
            $params[] = $now;
            $params[] = $record['event']->eventId()->toString();
        }
        $this->logger->debug('preparing insert sql');
        /** @var Statement $statement */
        $statement = yield $this->pool->prepare($sql);
        $this->logger->debug('executing insert sql');
        /** @var CommandResult $result */
        $result = yield $statement->execute($params);

        if (0 === $result->affectedRows()) {
            throw new Exception\RuntimeException('Could not create links');
        }

        $this->logger->debug('releasing locks');

        foreach ($streams as $stream) {
            yield new Coroutine($this->releaseLock($this->lockConnection, $stream));
        }

        $this->logger->debug('releasing locks done');

        Loop::defer(function (): Generator {
            $this->logger->debug('next write loop called');
            yield new Coroutine($this->write());
        });

        $this->logger->debug('writing ok');
    }

    /** @throws Throwable */
    private function runQuery(): Generator
    {
        $this->logger->debug('eval query');

        eval($this->query);
        $this->projectionState = ProjectionState::running();

        /** @var EventReader $reader */
        $reader = yield $this->determineReader();
        $reader->run();

        $this->logger->debug('reader run');

        $init = true;
        foreach ($this->streamPositions as $streamName => $position) {
            if ($position > 0) {
                $init = false;
                break;
            }
        }

        if ($init && isset($this->handlers['$init'])) {
            $initHandler = $this->handlers['$init'];
            $this->state = $initHandler();
        }

        Loop::delay(100, function (): Generator { // write up to 10 times per second
            yield from $this->write();
        });

        Loop::delay($this->checkpointAfterMs, function (): Generator {
            yield from $this->writeCheckPoint();
        });

        while (! $reader->eof()) {
            $this->logger->debug('handle loop');
            if (! $this->queue->isEmpty()) {
                /** @var RecordedEvent $event */
                $event = $this->queue->dequeue();
                $this->handle($event);
            }

            if (! $this->projectionState->equals(ProjectionState::running())) {
                $reader->pause();
                break;
            }

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
            $this->handle($event);

            yield new Delayed(0); // let some other work be done, too
        }

        if ($this->currentBatchSize > 0) {
            yield from $this->writeCheckPoint();
        }

        $this->logger->info('written checkpoint');
    }

    /** @throws Throwable */
    private function handle(RecordedEvent $event): void
    {
        $handle = function (callable $handler, RecordedEvent $event): void {
            $state = $handler($this->state, $event);

            if (is_array($state)) {
                $this->state = $state;
            }
        };

        $handled = false;

        /**
         for (var name in handlers) {
             if (name == 0 || name === "$init") {
                 eventProcessor.on_init_state(handlers[name]);
             } else if (name === "$initShared") {
                 eventProcessor.on_init_shared_state(handlers[name]);
             } else if (name === "$any") {
                 eventProcessor.on_any(handlers[name]);
             } else if (name === "$deleted") {
                 eventProcessor.on_deleted_notification(handlers[name]);
             } else if (name === "$created") {
                 eventProcessor.on_created_notification(handlers[name]);
             } else {
                 eventProcessor.on_event(name, handlers[name]);
             }
         }
         */

        if (isset($this->handlers['$any'])) {
            $handler = $this->handlers['$any'];
            $handle($handler, $event);
            $handled = true;
        }

        if (isset($this->handlers[$event->eventType()])) {
            $handler = $this->handlers[$event->eventType()];
            $handle($handler, $event);
            $handled = true;
        }

        $this->streamPositions[$event->streamId()] = $event->eventNumber();

        if ($handled) {
            ++$this->currentBatchSize;
        }

        $this->logger->debug('handled event ' . $event->eventNumber());
    }

    /** @throws Throwable */
    private function writeCheckPoint(): Generator
    {
        if ($this->currentBatchSize < $this->checkpointHandledThreshold
            || (
                0 !== $this->checkpointAfterMs
                || (floor(microtime(true) * 1000 - $this->lastCheckPointMs) < $this->checkpointAfterMs)
            )
        ) {
            if ($this->projectionState->equals(ProjectionState::running())) {
                Loop::delay($this->checkpointAfterMs, function (): Generator {
                    yield from $this->writeCheckPoint();
                });
            }

            return null;
        }

        $checkpointStream = ProjectionNames::ProjectionsStreamPrefix . $this->name . ProjectionNames::ProjectionCheckpointStreamSuffix;

        try {
            $streamId = yield $this->determineStreamId($checkpointStream);
        } catch (StreamNotFound $e) {
            $streamId = Uuid::uuid4()->toString();
            /** @var Statement $statement */
            $statement = yield $this->pool->prepare(<<<SQL
INSERT INTO streams (stream_id, stream_name, mark_deleted, deleted) VALUES (?, ?, ?, ?);
SQL
            );
            /** @var CommandResult $result */
            $result = yield $statement->execute([$streamId, $checkpointStream, 0, 0]);

            if (0 === $result->affectedRows()) {
                throw new Exception\RuntimeException('Could not create stream id for ' . $checkpointStream);
            }
        }

        yield new Coroutine($this->acquireLock($this->lockConnection, $checkpointStream));

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare(<<<SQL
SELECT MAX(event_number) as current_version FROM events WHERE stream_id = ?
SQL
        );
        /** @var ResultSet $result */
        $result = yield $statement->execute([$streamId]);

        $expectedVersion = ExpectedVersion::NoStream;

        while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
            $data = $result->getCurrent();
            $expectedVersion = $data->current_version;
        }

        $sql = <<<SQL
INSERT INTO events (event_id, event_number, event_type, data, meta_data, stream_id, is_json, updated)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?);
SQL;
        $now = new DateTimeImmutable('NOW', new DateTimeZone('UTC'));
        $now = DateTimeUtil::format($now);

        $params[] = EventId::generate()->toString();
        $params[] = ++$expectedVersion;
        $params[] = ProjectionEventTypes::ProjectionCheckpoint;
        $params[] = json_encode([
            '$s' => $this->streamPositions,
        ]);
        $params[] = '';
        $params[] = $streamId;
        $params[] = true;
        $params[] = $now;

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare($sql);
        /** @var CommandResult $result */
        $result = yield $statement->execute($params);

        if (0 === $result->affectedRows()) {
            throw new Exception\RuntimeException('Could not write checkpoint for ' . $this->name);
        }

        $this->lastCheckPointMs = microtime(true) * 10000;

        yield new Coroutine($this->releaseLock($this->lockConnection, $checkpointStream));

        if ($this->projectionState->equals(ProjectionState::running())) {
            Loop::delay($this->checkpointAfterMs, function (): Generator {
                yield from $this->writeCheckPoint();
            });
        }
    }

    /** @throws Throwable */
    private function determineReader(): Promise
    {
        return call(function (): Generator {
            if (isset($this->evaledQuery['streams']) && 1 === count($this->evaledQuery['streams'])) {
                $streamName = current($this->evaledQuery['streams']);

                if (! isset($this->streamPositions[$streamName])) {
                    $this->streamPositions[$streamName] = 0;
                }

                $streamId = yield $this->determineStreamId($streamName);

                return yield new Success(new StreamEventReader(
                    $this->pool,
                    $this->queue,
                    'Continuous' !== $this->mode,
                    $streamName,
                    $streamId,
                    $this->streamPositions[$streamName],
                    $this->pendingEventsThreshold
                ));
            }

            // @todo implement
            throw new Exception\RuntimeException('Not implemented');
        });
    }

    /** @throws Throwable */
    private function determineStreamId(string $streamName): Promise
    {
        $streamId = $this->knownStreamIds->get($streamName);

        if (null !== $streamId) {
            return new Success($streamId);
        }

        return call(function () use ($streamName): Generator {
            /** @var Statement $statement */
            $statement = yield $this->pool->prepare(<<<SQL
SELECT streams.stream_id, streams.mark_deleted, streams.deleted, STRING_AGG(stream_acl.role, ',') as stream_roles
FROM streams
LEFT JOIN stream_acl ON streams.stream_id = stream_acl.stream_id AND stream_acl.operation = ?
WHERE streams.stream_name = ?
GROUP BY streams.stream_id, streams.mark_deleted, streams.deleted
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

                $this->knownStreamIds->put($streamName, $data->stream_id);

                return $data->stream_id;
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

    // following is stuff executable from within projection query

    /** @throws Throwable */
    public function emit(string $stream, string $eventType, string $data, string $metadata): Promise
    {
        // @todo refactor must return void, not a promise
        return call(function () use ($stream, $eventType, $data, $metadata): Generator {
            $isJson = false;
            json_decode($data);

            if (0 === json_last_error()) {
                $isJson = true;
            }

            yield new Coroutine($this->acquireLock($this->lockConnection, $stream));

            $expectedVersion = ExpectedVersion::NoStream;

            try {
                $streamId = yield $this->determineStreamId($stream);

                /** @var Statement $statement */
                $statement = yield $this->pool->prepare(<<<SQL
SELECT MAX(event_number) as current_version FROM events WHERE stream_id = ?
SQL
                );
                /** @var ResultSet $result */
                $result = yield $statement->execute([$streamId]);

                while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
                    $expectedVersion = $result->getCurrent()->current_version;
                }
            } catch (StreamNotFound $e) {
                $streamId = Uuid::uuid4()->toString();
                /** @var Statement $statement */
                $statement = yield $this->pool->prepare(<<<SQL
INSERT INTO streams (stream_id, stream_name, mark_deleted, deleted) VALUES (?, ?, ?, ?);
SQL
                );
                /** @var CommandResult $result */
                $result = yield $statement->execute([$streamId, $stream, 0, 0]);

                if (0 === $result->affectedRows()) {
                    throw new Exception\RuntimeException('Could not create stream id for ' . $stream);
                }
            }

            $sql = <<<SQL
INSERT INTO events (event_id, event_number, event_type, data, meta_data, stream_id, is_json, updated)
VALUES (?, ?, ?, ?, ?, ?, ?, ?);
SQL;
            $now = new DateTimeImmutable('NOW', new DateTimeZone('UTC'));
            $now = DateTimeUtil::format($now);

            $params[] = EventId::generate();
            $params[] = ++$expectedVersion;
            $params[] = $eventType;
            $params[] = $data;
            $params[] = $metadata;
            $params[] = $streamId;
            $params[] = $isJson;
            $params[] = $now;

            /** @var Statement $statement */
            $statement = yield $this->pool->prepare($sql);
            /** @var CommandResult $result */
            $result = yield $statement->execute($params);

            if (0 === $result->affectedRows()) {
                throw new Exception\RuntimeException('Could not emit to stream ' . $stream);
            }

            yield new Coroutine($this->releaseLock($this->lockConnection, $stream));
        });
    }

    /** @throws Throwable */
    public function linkTo(string $stream, RecordedEvent $event, string $metadata = ''): void
    {
        if (! in_array($stream, $this->toWriteStreams, true)) {
            $this->toWriteStreams[] = $stream;
        }

        $this->toWrite[] = [
            'stream' => $stream,
            'event' => $event,
            'metadata' => $metadata,
        ];
    }

    // Allows only the given events of a particular to pass through.
    // expects array with key = event-type, and value = function (array $state, RecordedEvent $event): array|void
    // about keys:
    // $init - Provide the initialization for a projection.
    // $any - Event type pattern match that will match any event type.
    // @todo: implementation detail of original is:
    // When using fromAll() and 2 or more event type handlers are specified and the $by_event_type projection
    // is enabled and running, the projection will start as a fromStreams('$et-event-type-foo', $et-event-type-bar)
    // until the projection has caught up and then will move over to reading from the transaction log (i.e. from $all).
    private function when(array $handlers): Projection
    {
        /*
        function translateOn(handlers) {
            for (var name in handlers) {
                if (name == 0 || name === "$init") {
                    eventProcessor.on_init_state(handlers[name]);
                } else if (name === "$initShared") {
                    eventProcessor.on_init_shared_state(handlers[name]);
                } else if (name === "$any") {
                    eventProcessor.on_any(handlers[name]);
                } else if (name === "$deleted") {
                    eventProcessor.on_deleted_notification(handlers[name]);
                } else if (name === "$created") {
                    eventProcessor.on_created_notification(handlers[name]);
                } else {
                    eventProcessor.on_event(name, handlers[name]);
                }
            }
        }

        function when(handlers) {
            translateOn(handlers);
            return {
                $defines_state_transform: $defines_state_transform,
                transformBy: transformBy,
                filterBy: filterBy,
                outputTo: outputTo,
                outputState: outputState,
            };
        }
         */
        foreach ($handlers as $name => $callback) {
            if (! is_string($name) || ! is_callable($callback)) {
                throw new Exception\RuntimeException('Invalid argument passed to when()');
            }

            $this->handlers[$name] = Closure::bind($callback, $this->createHandlerContext());
        }

        return $this;
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

    private function fromStream(string $streamName): Projection
    {
        /*
        function fromStream(stream) {
            eventProcessor.fromStream(stream);
            return {
                partitionBy: partitionBy,
                when: when,
                outputState: outputState,
            };
        }
         */
        $this->evaledQuery = ['streams' => [$streamName]];

        return $this;
    }

    private function fromStreams(string ...$streamNames): Projection
    {
        /*
        function fromStreams(streams) {
            var arr = Array.isArray(streams) ? streams : arguments;
            for (var i = 0; i < arr.length; i++)
                eventProcessor.fromStream(arr[i]);

            return {
                partitionBy: partitionBy,
                when: when,
                outputState: outputState,
            };
        }
         */
        $this->evaledQuery = ['streams' => $streamNames];

        return $this;
    }

    private function fromCategory(string $name): Projection
    {
        /*
        function fromCategory(category) {
            eventProcessor.fromCategory(category);
            return {
                partitionBy: partitionBy,
                foreachStream: foreachStream,
                when: when,
                outputState: outputState,
            };
        }
         */
        $this->evaledQuery = ['categories' => [$name]];

        return $this;
    }

    private function fromCategories(string ...$names): Projection
    {
        /**
        function fromCategories(categories) {
            var arr = Array.isArray(categories) ? categories : Array.prototype.slice.call(arguments);
            arr = arr.map(function (x) {
                return '$ce-' + x;
            });
            return fromStreams(arr);
        }
         */
        $this->evaledQuery = ['categories' => $names];

        return $this;
    }

    private function fromAll(): Projection
    {
        /*
        function fromAll() {
            eventProcessor.fromAll();
            return {
                partitionBy: partitionBy,
                when: when,
                foreachStream: foreachStream,
                outputState: outputState,
            };
        }
         */
        $this->evaledQuery = ['all' => true];

        return $this;
    }

    // Partitions the state for each of the streams provided.
    private function foreachStream(): Projection
    {
        // @todo implement
        /*
        function foreachStream() {
            eventProcessor.byStream();
            return {
                when: when,
            };
        }
         */
    }

    // Selects events from the $all stream that returns true for the given filter.
    private function fromStreamsMatching(callable $filter): Projection
    {
        // @todo implement
        /*
        function fromStreamsMatching(filter) {
            eventProcessor.fromStreamsMatching(filter);
            return {
                when: when,
            };
        }
         */
    }

    // requires a callable with: function (array $state): array
    // Provides the ability to transform the state of a projection by the provided handler.
    private function transformBy(callable $transformer): Projection
    {
        // @todo implement
        /*
        function transformBy(by) {
            eventProcessor.chainTransformBy(by);
            return {
                transformBy: transformBy,
                filterBy: filterBy,
                outputState: outputState,
                outputTo: outputTo,
            };
        }
         */
    }

    // Causes projection results to be null for any state that returns a falsey value from the given predicate.
    private function filterBy(callable $by): Projection
    {
        // @todo implement
        /*
        function filterBy(by) {
            eventProcessor.chainTransformBy(function (s) {
                var result = by(s);
                return result ? s : null;
            });
            return {
                transformBy: transformBy,
                filterBy: filterBy,
                outputState: outputState,
                outputTo: outputTo,
            };
        }
         */
    }

    /*
     function outputTo(resultStream, partitionResultStreamPattern) {
        eventProcessor.$defines_state_transform();
        eventProcessor.options({
            resultStreamName: resultStream,
            partitionResultStreamNamePattern: partitionResultStreamPattern,
        });
    }

    function outputState() {
        eventProcessor.$outputState();
        return {
            transformBy: transformBy,
            filterBy: filterBy,
            outputTo: outputTo,
        };
    }
     */

    private function createHandlerContext(): object
    {
        return new class($this) {
            private $projector;

            public function __construct(Projection $projector)
            {
                $this->projector = $projector;
            }

            public function stop(): void
            {
                $this->projector->disable();
            }

            public function linkTo(string $streamName, RecordedEvent $event, string $metadata = ''): void
            {
                /*
                function linkTo(streamId, event, metadata) {
                    var message = { streamId: streamId, eventName: "$>", body: event.sequenceNumber + "@" + event.streamId, metadata: metadata, isJson: false };
                    eventProcessor.emit(message);
                }
                 */
                $this->projector->linkTo($streamName, $event, $metadata);
            }

            public function emit(string $streamName, string $eventType, string $data, string $metadata = ''): void
            {
                /*
                function emit(streamId, eventName, eventBody, metadata) {
                    var message = { streamId: streamId, eventName: eventName , body: JSON.stringify(eventBody), metadata: metadata, isJson: true };
                    eventProcessor.emit(message);
                }
                 */
                $this->projector->emit($streamName, $eventType, $data, $metadata);
            }
        };
    }
}
