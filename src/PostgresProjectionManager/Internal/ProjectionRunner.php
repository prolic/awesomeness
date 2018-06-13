<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Internal;

use Amp\Deferred;
use Amp\Loop;
use Amp\Postgres\CommandResult;
use Amp\Postgres\Connection;
use Amp\Postgres\Pool;
use Amp\Postgres\ResultSet;
use Amp\Postgres\Statement;
use Amp\Promise;
use Amp\Success;
use Amp\Sync\LocalMutex;
use Amp\Sync\Lock;
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

/**
 * @internal
 */
class ProjectionRunner
{
    private const CheckedStreamsCacheSize = 10000;
    private const MinCheckpointAfterMs = 100;
    private const MaxMaxWriteBatchLength = 5000;

    // properties regarding projection runner

    /** @var SplQueue */
    private $processingQueue;
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
    /** @var EventProcessor */
    private $processor;
    /** @var Deferred */
    private $shutdownDeferred;
    /** @var Promise */
    private $shutdownPromise;
    /** @var LocalMutex */
    private $readMutex;
    /** @var LocalMutex */
    private $persistMutex;
    /** @var int */
    private $projectionEventNumber;
    /** @var int */
    private $lastCheckPointMs;
    /** @var LRUCache */
    private $checkedStreams;
    /** @var array */
    private $streamsOfEmittedEvents = [];
    /** @var array */
    private $emittedEvents = [];
    /** @var int */
    private $currentBatchSize = 0;

    // properties regarding projection itself

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
    private $maxWriteBatchLength;
    /** @var int */
    private $checkpointAfterMs = self::MinCheckpointAfterMs;
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
    /** @var array|null */
    private $loadedState;

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
        $this->processingQueue = new SplQueue();
        $this->shutdownDeferred = new Deferred();
        $this->shutdownPromise = $this->shutdownDeferred->promise();
        $this->readMutex = new LocalMutex();
        $this->persistMutex = new LocalMutex();
    }

    /** @throws Throwable */
    public function bootstrap(): Promise
    {
        return call(function (): Generator {
            yield from $this->load();

            if ($this->enabled) {
                yield $this->enable();
            }
        });
    }

    /** @throws Throwable */
    private function load(): Generator
    {
        $this->logger->debug('Loading projection');

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
        if (\is_string($data->stream_roles)) {
            $toCheck = \explode(',', $data->stream_roles);
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
                    $data = \json_decode($event->data, true);

                    if (0 !== \json_last_error()) {
                        throw new Error('Could not json decode event data for projection');
                    }

                    $this->handlerType = $data['handlerType'];
                    $this->query = $data['query'];
                    $this->mode = $data['mode'];
                    $this->enabled = $data['enabled'] ?? false;
                    $this->emitEnabled = $data['emitEnabled'];
                    $this->checkpointsDisabled = $data['checkpointsDisabled'];
                    $this->trackEmittedStreams = $data['trackEmittedStreams'];
                    $this->maxWriteBatchLength = \min($data['maxWriteBatchLength'], self::MaxMaxWriteBatchLength);
                    if (isset($data['checkpointAfterMs'])) {
                        $this->checkpointAfterMs = \max($data['checkpointAfterMs'], self::MinCheckpointAfterMs);
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
                    $this->projectionEventNumber = $event->event_number;
                    break;
                case '$stop':
                    $this->enabled = false;
                    $this->projectionEventNumber = $event->event_number;
                    break;
                case '$start':
                    $this->enabled = true;
                    $this->projectionEventNumber = $event->event_number;
                    break;
            }
        }

        if ('$system' !== $this->runAs['name']
            && ! \in_array($this->runAs['name'], $toCheck)
            && (! isset($this->runAs['roles']) || empty(\array_intersect($this->runAs['roles'], $toCheck)))
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
                $state = \json_decode($data->data, true);
                $streamPositions = \json_decode($data->meta_data, true);

                if (0 !== \json_last_error()) {
                    throw new Exception\RuntimeException('Could not json decode checkpoint');
                }

                $this->loadedState = $state;
                $this->streamPositions = $streamPositions['$s'];
            }
        } catch (StreamNotFound $e) {
            // ignore, no checkpoint found
        }
    }

    /** @throws Throwable */
    public function enable(): Promise
    {
        if (! $this->state->equals(ProjectionState::initial())
            && ! $this->state->equals(ProjectionState::stopped())
        ) {
            throw new Error('Cannot start projection: already ' . $this->state->name());
        }

        return call(function (): Generator {
            if (! $this->enabled) {
                $sql = <<<SQL
INSERT INTO events (stream_name, event_id, event_number, event_type, data, meta_data, is_json, updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?);
SQL;
                /** @var Statement $statement */
                $statement = yield $this->pool->prepare($sql);

                $now = new DateTimeImmutable('NOW', new DateTimeZone('UTC'));

                /** @var CommandResult $result */
                $result = yield $statement->execute([
                    ProjectionNames::ProjectionsStreamPrefix . $this->name,
                    EventId::generate()->toString(),
                    $this->projectionEventNumber + 1,
                    '$start',
                    \json_encode(['id' => $this->id]),
                    '',
                    true,
                    DateTimeUtil::format($now),
                ]);

                if ($result->affectedRows() !== 1) {
                    throw new Exception\RuntimeException('Could not enable projection');
                }

                ++$this->projectionEventNumber;

                $this->enabled = true;
            }

            yield from $this->runQuery();
        });
    }

    public function disable(): Promise
    {
        if (! $this->state->equals(ProjectionState::running())) {
            return new Success();
        }

        $this->enabled = false;
        $this->state = ProjectionState::stopping();

        return call(function (): Generator {
            $sql = <<<SQL
INSERT INTO events (stream_name, event_id, event_number, event_type, data, meta_data, is_json, updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?);
SQL;
            /** @var Statement $statement */
            $statement = yield $this->pool->prepare($sql);

            $now = new DateTimeImmutable('NOW', new DateTimeZone('UTC'));

            /** @var CommandResult $result */
            $result = yield $statement->execute([
                ProjectionNames::ProjectionsStreamPrefix . $this->name,
                EventId::generate()->toString(),
                $this->projectionEventNumber + 1,
                '$stop',
                \json_encode(['id' => $this->id]),
                '',
                true,
                DateTimeUtil::format($now),
            ]);

            if ($result->affectedRows() !== 1) {
                throw new Exception\RuntimeException('Could not disable projection');
            }

            ++$this->projectionEventNumber;
        });
    }

    public function shutdown(): Promise
    {
        if ($this->state->equals(ProjectionState::stopped())) {
            $this->logger->info('shutdown done');
            $this->shutdownDeferred->resolve();

            return $this->shutdownPromise;
        }

        if ($this->state->equals(ProjectionState::initial())) {
            $this->state = ProjectionState::stopped();
            $this->logger->info('shutdown done');
            $this->shutdownDeferred->resolve();

            return $this->shutdownPromise;
        }

        $this->state = ProjectionState::stopping();

        Loop::repeat(100, function (string $watcherId): void {
            if ($this->state->equals(ProjectionState::stopped())) {
                Loop::cancel($watcherId);
                $this->logger->info('shutdown done');
                $this->shutdownDeferred->resolve();
            } else {
                $this->logger->debug('still waiting for shutdown...');
            }
        });

        return $this->shutdownPromise;
    }

    /** @throws Throwable */
    private function runQuery(): Generator
    {
        $this->logger->debug('Running projection');

        if ($this->emitEnabled) {
            $notify = function (string $streamName, string $eventType, string $data, string $metadata = '', bool $isJson = false): void {
                $this->emit($streamName, $eventType, $data, $metadata, $isJson);
            };
        } else {
            $notify = function () {
                throw new \RuntimeException('\'emit\' is not allowed by the projection/configuration/mode');
            };
        }

        $evaluator = new ProjectionEvaluator($notify);
        $this->processor = $evaluator->evaluate($this->query);
        $this->processor->initState($this->loadedState);

        $this->state = ProjectionState::running();

        /** @var EventReader $reader */
        $reader = yield $this->determineReader();
        $reader->run();

        $this->lastCheckPointMs = \microtime(true) * 10000;

        $readingTask = function () use (&$readingTask, $reader): void {
            if ($reader->eof()) {
                $this->persist(
                    $this->emittedEvents,
                    $this->streamsOfEmittedEvents,
                    $this->processor->getState(),
                    $this->streamPositions,
                    false
                );

                return;
            }

            if ($this->state->equals(ProjectionState::stopping())) {
                $reader->pause();

                $this->persist(
                    $this->emittedEvents,
                    $this->streamsOfEmittedEvents,
                    $this->processor->getState(),
                    $this->streamPositions,
                    true
                );

                return;
            }

            if ($this->processingQueue->isEmpty()) {
                $this->persist(
                    $this->emittedEvents,
                    $this->streamsOfEmittedEvents,
                    $this->processor->getState(),
                    $this->streamPositions,
                    false
                );

                Loop::delay(200, $readingTask); // if queue is empty let's wait for a while

                return;
            }

            /** @var RecordedEvent $event */
            $event = $this->processingQueue->dequeue();
            $this->processor->processEvent($event);
            $this->streamPositions[$event->streamId()] = $event->eventNumber();
            ++$this->currentBatchSize;

            if ($this->currentBatchSize >= $this->checkpointHandledThreshold
                && (\floor(\microtime(true) * 10000 - $this->lastCheckPointMs) >= $this->checkpointAfterMs)
            ) {
                $this->persist(
                    $this->emittedEvents,
                    $this->streamsOfEmittedEvents,
                    $this->processor->getState(),
                    $this->streamPositions,
                    false
                );
            }

            if ($this->processingQueue->count() > $this->pendingEventsThreshold) {
                $reader->pause();
            }

            if ($this->processingQueue->count() < $this->pendingEventsThreshold
                && $reader->paused()
            ) {
                $reader->run();
            }

            Loop::defer($readingTask);
        };

        Loop::defer($readingTask);
    }

    private function persist(
        array $emittedEvents,
        array $streamsOfEmittedEvents,
        array $state,
        array $streamPositions,
        bool $stop
    ): void {
        if (0 === $this->currentBatchSize) {
            if ($stop) {
                Loop::defer(function (): Generator {
                    /** @var Lock $lock */
                    $lock = yield $this->persistMutex->acquire();

                    $this->loadedState = $this->processor->getState();
                    $this->state = ProjectionState::stopped();

                    $lock->release();
                });
            }

            return;
        }

        $this->emittedEvents = [];
        $this->streamsOfEmittedEvents = [];
        $this->currentBatchSize = 0;
        $this->lastCheckPointMs = \microtime(true) * 10000;

        Loop::defer(function () use (
            $emittedEvents,
            $streamsOfEmittedEvents,
            $state,
            $streamPositions,
            $stop
        ): Generator {
            /** @var Lock $lock */
            $lock = yield $this->persistMutex->acquire();

            $checkpointStream = ProjectionNames::ProjectionsStreamPrefix . $this->name . ProjectionNames::ProjectionCheckpointStreamSuffix;

            /** @var Connection $lockConnection */
            $lockConnection = yield $this->pool->extractConnection();

            foreach ($streamsOfEmittedEvents as $stream) {
                yield from $this->acquireLock($lockConnection, $stream);
            }

            yield from $this->acquireLock($lockConnection, $checkpointStream);
            yield from $this->writeEmittedEvents($emittedEvents, $streamsOfEmittedEvents);
            yield from $this->writeCheckPoint($checkpointStream, $state, $streamPositions);

            foreach ($streamsOfEmittedEvents as $stream) {
                yield from $this->releaseLock($lockConnection, $stream);
            }

            if ($stop) {
                $this->loadedState = $this->processor->getState();
                $this->state = ProjectionState::stopped();
            }

            yield from $this->releaseLock($lockConnection, $checkpointStream);

            $lockConnection->close();

            unset($lockConnection);

            $lock->release();
        });
    }

    /** @throws Throwable */
    private function writeEmittedEvents(array $emittedEvents, array $streamsOfEmittedEvents): Generator
    {
        if (0 === \count($emittedEvents)) {
            return null;
        }

        $expectedVersions = [];

        foreach ($streamsOfEmittedEvents as $stream) {
            $expectedVersion = ExpectedVersion::NoStream;

            try {
                yield $this->checkStream($stream);
                $expectedVersion = yield from $this->getExpectedVersion($stream);
            } catch (StreamNotFound $e) {
                yield from $this->createStream($stream);
            }

            $expectedVersions[$stream] = $expectedVersion;
        }

        foreach (\array_chunk($emittedEvents, $this->maxWriteBatchLength) as $batch) {
            $params = [];
            foreach ($batch as $record) {
                foreach ($record as $row) {
                    $params[] = $row;
                }

                $now = new DateTimeImmutable('NOW', new DateTimeZone('UTC'));
                $params[] = ++$expectedVersions[$record['stream']];
                $params[] = DateTimeUtil::format($now);
            }

            $sql = <<<SQL
INSERT INTO events (stream_name, event_id, event_type, data, meta_data, is_json, link_to_stream_name, link_to_event_number, event_number, updated)
VALUES 
SQL;
            $sql .= \str_repeat('(?, ?, ?, ?, ?, ?, ?, ?, ?, ?), ', \count($batch));
            $sql = \substr($sql, 0, -2) . ';';

            /** @var Statement $statement */
            $statement = yield $this->pool->prepare($sql);
            /** @var CommandResult $result */
            $result = yield $statement->execute($params);

            if (0 === $result->affectedRows()) {
                throw new Exception\RuntimeException('Could not write events');
            }
        }
    }

    /** @throws Throwable */
    private function writeCheckPoint(string $checkpointStream, array $state, array $streamPositions): Generator
    {
        try {
            yield $this->checkStream($checkpointStream);
        } catch (StreamNotFound $e) {
            yield from $this->createStream($checkpointStream);
        }

        $expectedVersion = yield from $this->getExpectedVersion($checkpointStream);

        $sql = <<<SQL
INSERT INTO events (event_id, event_number, event_type, data, meta_data, stream_name, is_json, updated) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?);
SQL;
        $now = new DateTimeImmutable('NOW', new DateTimeZone('UTC'));

        $params[] = EventId::generate()->toString();
        $params[] = ++$expectedVersion;
        $params[] = ProjectionEventTypes::ProjectionCheckpoint;
        $params[] = \json_encode($state);
        $params[] = \json_encode([
            '$s' => $streamPositions,
        ]);
        $params[] = $checkpointStream;
        $params[] = true;
        $params[] = DateTimeUtil::format($now);

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare($sql);
        /** @var CommandResult $result */
        $result = yield $statement->execute($params);

        if (0 === $result->affectedRows()) {
            throw new Exception\RuntimeException('Could not write checkpoint');
        }

        $this->logger->info('Checkpoint created');
    }

    /** @throws Throwable */
    private function determineReader(): Promise
    {
        return call(function (): Generator {
            if (\count($this->processor->sources()['streams']) === 1) {
                $streamName = \current($this->processor->sources()['streams']);

                if (! isset($this->streamPositions[$streamName])) {
                    $this->streamPositions[$streamName] = -1;
                }

                yield $this->checkStream($streamName);

                return yield new Success(new StreamEventReader(
                    $this->readMutex,
                    $this->pool,
                    $this->processingQueue,
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

                if (\is_string($data->stream_roles)) {
                    $toCheck = \explode(',', $data->stream_roles);
                }

                if ('$system' !== $this->runAs['name']
                    && ! \in_array($this->runAs['name'], $toCheck)
                    && (! isset($this->runAs['roles']) || empty(\array_intersect($this->runAs['roles'], $toCheck)))
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

    /** @throws Throwable */
    private function emit(string $stream, string $eventType, string $data, string $metadata, bool $isJson = false): void
    {
        if (! \in_array($stream, $this->streamsOfEmittedEvents, true)) {
            $this->streamsOfEmittedEvents[] = $stream;
        }

        $eventData = [
            'stream' => $stream,
            'eventId' => EventId::generate()->toString(),
            'eventType' => $eventType,
            'data' => $data,
            'metadata' => $metadata,
            'isJson' => $isJson,
            'link_to_stream_name' => null,
            'link_to_event_number' => null,
        ];

        if ($eventType === SystemEventTypes::LinkTo) {
            $linkTo = \explode('@', $data);

            $eventData['link_to_stream_name'] = $linkTo[1];
            $eventData['link_to_event_number'] = $linkTo[0];
        }

        $this->emittedEvents[] = $eventData;
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

    public function getConfig(): array
    {
        return [
            'emitEnabled' => $this->emitEnabled,
            'checkpointHandledThreshold' => $this->checkpointHandledThreshold,
            'checkpointUnhandledBytesThreshold' => $this->checkpointUnhandledBytesThreshold,
            'pendingEventsThreshold' => $this->pendingEventsThreshold,
            'maxWriteBatchLength' => $this->maxWriteBatchLength,
        ];
    }

    public function getDefinition(): array
    {
        /*
         {
  "msgTypeId": 298,
  "name": "mixedprojection",
  "query": "fromStream('sasastream4').when({\n    $init: function () {\n        return {count: 0};\n    },\n    $any: function (s, e) {\n        linkTo('testlinkstreamxxx', e);\n        s.count++;\n        return s;\n    }\n})",
  "definition": {
    "allEvents": true,
    "categories": [],
    "events": [],
    "streams": [
      "sasastream4"
    ],
    "options": {
      "definesFold": true
    }
  },
  "outputConfig": {
    "resultStreamName": "$projections-mixedprojection-result"
  }
}%
         */

        return [
            'name' => $this->name,
            'query' => $this->query,
            'definition' => $this->processor->sources(),
        ];
    }

    public function getState(): array
    {
        return $this->processor->getState();
    }

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
