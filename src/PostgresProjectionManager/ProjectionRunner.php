<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager;

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
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\ProjectionManagement\Internal\ProjectionConfig;
use Prooph\EventStore\ProjectionManagement\ProjectionDefinition;
use Prooph\EventStore\Projections\ProjectionEventTypes;
use Prooph\EventStore\Projections\ProjectionMode;
use Prooph\EventStore\Projections\ProjectionNames;
use Prooph\EventStore\Projections\ProjectionState;
use Prooph\EventStore\RecordedEvent;
use Prooph\PostgresProjectionManager\Exception\QueryEvaluationError;
use Prooph\PostgresProjectionManager\Exception\StreamNotFound;
use Prooph\PostgresProjectionManager\Operations\CreateStreamOperation;
use Prooph\PostgresProjectionManager\Operations\GetExpectedVersionOperation;
use Prooph\PostgresProjectionManager\Operations\LoadConfigOperation;
use Prooph\PostgresProjectionManager\Operations\LoadConfigResult;
use Prooph\PostgresProjectionManager\Operations\LoadLatestCheckpointOperation;
use Prooph\PostgresProjectionManager\Operations\LoadLatestCheckpointResult;
use Prooph\PostgresProjectionManager\Operations\LoadProjectionStreamRolesOperation;
use Psr\Log\LoggerInterface as PsrLogger;
use SplQueue;
use Throwable;
use function Amp\call;

/** @internal */
class ProjectionRunner
{
    private const CheckedStreamsCacheSize = 10000;

    /** @var string */
    private $id;
    /** @var ProjectionConfig */
    private $config;
    /** @var ProjectionDefinition */
    private $definition;
    /** @var SplQueue */
    private $processingQueue;
    /** @var array */
    private $operations = [];
    /** @var Pool */
    private $pool;
    /** @var Connection */
    private $lockConnection;
    /** @var PsrLogger */
    private $logger;
    /** @var ProjectionState */
    private $state;
    /** @var string */
    private $stateReason = '';
    /** @var EventProcessor */
    private $processor;
    /** @var EventReader */
    private $reader;
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
    private $lastCheckpointMs;
    /** @var LRUCache */
    private $checkedStreams;
    /** @var array */
    private $streamsOfEmittedEvents = [];
    /** @var array */
    private $emittedEvents = [];
    /** @var int */
    private $eventsProcessedAfterRestart = 0;
    /** @var int */
    private $currentBatchSize = 0;
    /** @var StatisticsRecorder */
    private $statisticsRecorder;
    /** @var string */
    private $checkpointStatus = '';
    /** @var int */
    private $currentlyWriting = 0;
    /** @var array|null */
    private $loadedState;
    /** @var ProjectionMode */
    private $mode;
    /** @var bool */
    private $enabled;
    /** @var array */
    private $streamPositions = [];
    /** @var array */
    private $lastCheckpoint = [];

    public function __construct(string $id, Pool $pool, PsrLogger $logger)
    {
        $this->id = $id;
        $this->pool = $pool;
        $this->logger = $logger;
        $this->state = ProjectionState::stopped();
        $this->checkedStreams = new LRUCache(self::CheckedStreamsCacheSize);
        $this->processingQueue = new SplQueue();
        $this->shutdownDeferred = new Deferred();
        $this->shutdownPromise = $this->shutdownDeferred->promise();
        $this->readMutex = new LocalMutex();
        $this->persistMutex = new LocalMutex();
        $this->statisticsRecorder = new StatisticsRecorder();
    }

    /** @throws Throwable */
    public function bootstrap(string $name): Promise
    {
        return call(function () use ($name): Generator {
            yield from $this->load($name);

            if ($this->enabled && $this->state->equals(ProjectionState::initial())) {
                yield $this->enable();
            }
        });
    }

    /** @throws Throwable */
    private function load(string $name): Generator
    {
        $this->logger->debug('Loading projection');

        $this->state = ProjectionState::initial();

        $this->lockConnection = yield $this->pool->extractConnection();

        $projectionStream = ProjectionNames::ProjectionsStreamPrefix . $name;

        $streamRoles = yield from (new LoadProjectionStreamRolesOperation())($this->pool, $projectionStream);

        /** @var LoadConfigResult $result */
        $result = yield from (new LoadConfigOperation())($this->pool, $projectionStream);
        $this->config = $result->config();
        $this->mode = $result->mode();
        $this->enabled = $result->enabled();
        $this->projectionEventNumber = $result->projectionEventNumber();

        $this->checkAcl($streamRoles, $projectionStream);

        if ($this->config->checkpointsEnabled()) {
            $this->checkpointStatus = 'Reading';

            $latestCheckpoint = yield from (new LoadLatestCheckpointOperation())(
                $this->pool,
                $projectionStream . ProjectionNames::ProjectionCheckpointStreamSuffix
            );

            if ($latestCheckpoint instanceof LoadLatestCheckpointResult) {
                $this->loadedState = $latestCheckpoint->state();
                $this->streamPositions = $latestCheckpoint->streamPositions();
                $this->lastCheckpoint = $latestCheckpoint->streamPositions();
            }

            $this->checkpointStatus = '';
        }

        if ($this->config->emitEnabled()) {
            $notify = function (string $streamName, string $eventType, string $data, string $metadata = '', bool $isJson = false): void {
                $this->emit($streamName, $eventType, $data, $metadata, $isJson);
            };
        } else {
            $notify = function () {
                throw new \RuntimeException('\'emit\' is not allowed by the projection/configuration/mode');
            };
        }

        $evaluator = new ProjectionEvaluator($notify);

        try {
            $this->processor = $evaluator->evaluate($result->query());
        } catch (QueryEvaluationError $e) {
            $this->state = ProjectionState::stopped();
            $this->stateReason = $e->getMessage();

            return;
        }

        $this->processor->initState($this->loadedState);

        $sources = $this->processor->sources();
        $this->definition = new ProjectionDefinition(
            $name,
            $result->query(),
            $this->config->emitEnabled(),
            $sources,
            []
        );

        $this->reader = yield $this->determineReader();
    }

    /** @throws Throwable */
    public function enable(string $enableRunAs = null): Promise
    {
        if ('' !== $this->stateReason) {
            throw new Error('Cannot start projection: ' . $this->stateReason);
        }

        if (! $this->state->equals(ProjectionState::initial())
            && ! $this->state->equals(ProjectionState::stopped())
        ) {
            throw new Error('Cannot start projection: already ' . $this->state->name());
        }

        $this->logger->info('enabling projection');

        if ($enableRunAs) {
            $this->config->runAs()->setIdentity($enableRunAs);
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
                    ProjectionNames::ProjectionsStreamPrefix . $this->definition->name(),
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

            $this->runQuery();
        });
    }

    public function disable(): Promise
    {
        if (! $this->state->equals(ProjectionState::running())) {
            return new Success();
        }

        $this->logger->info('disabling projection');

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
                ProjectionNames::ProjectionsStreamPrefix . $this->definition->name(),
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

    private function runQuery(): void
    {
        $this->logger->debug('Running projection');

        $this->state = ProjectionState::running();

        $this->reader->run();

        $this->lastCheckpointMs = \microtime(true) * 10000;

        $readingTask = function () use (&$readingTask): void {
            if ($this->reader->eof()) {
                $this->persist(false);

                return;
            }

            if ($this->state->equals(ProjectionState::stopping())) {
                $this->reader->pause();

                $this->persist(true);

                return;
            }

            if ($this->processingQueue->isEmpty()) {
                $this->persist(false);

                Loop::delay(200, $readingTask); // if queue is empty let's wait for a while

                return;
            }

            /** @var RecordedEvent $event */
            $event = $this->processingQueue->dequeue();
            try {
                $this->processor->processEvent($event);
            } catch (Throwable $e) {
                $this->stateReason = $e->getMessage();
                $this->reader->pause();
                $this->persist(true);

                return;
            }
            $this->streamPositions[$event->streamId()] = $event->eventNumber();
            ++$this->currentBatchSize;
            ++$this->eventsProcessedAfterRestart;

            if ($this->currentBatchSize >= $this->config->checkpointHandledThreshold()
                && (\floor(\microtime(true) * 10000 - $this->lastCheckpointMs) >= $this->config->checkpointAfterMs())
            ) {
                $this->persist(false);
            }

            if ($this->processingQueue->count() > $this->config->pendingEventsThreshold()) {
                $this->reader->pause();
            }

            if ($this->processingQueue->count() < ($this->config->pendingEventsThreshold() / 2)
                && $this->reader->paused()
            ) {
                $this->reader->run();
            }

            Loop::defer($readingTask);
        };

        Loop::defer($readingTask);
    }

    private function persist(bool $stop): void
    {
        $emittedEvents = $this->emittedEvents;
        $streamsOfEmittedEvents = $this->streamsOfEmittedEvents;
        $state = $this->processor->getState();
        $streamPositions = $this->streamPositions;

        $this->emittedEvents = [];
        $this->streamsOfEmittedEvents = [];

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

        $this->currentBatchSize = 0;
        $this->lastCheckpointMs = \microtime(true) * 10000;

        Loop::defer(function () use (
            $emittedEvents,
            $streamsOfEmittedEvents,
            $state,
            $streamPositions,
            $stop
        ): Generator {
            /** @var Lock $lock */
            $lock = yield $this->persistMutex->acquire();

            /** @var Connection $lockConnection */
            $lockConnection = yield $this->pool->extractConnection();

            foreach ($streamsOfEmittedEvents as $stream) {
                yield from $this->acquireLock($lockConnection, $stream);
            }

            yield from $this->writeEmittedEvents($emittedEvents, $streamsOfEmittedEvents);

            if ($this->config->checkpointsEnabled()) {
                $checkpointStream = ProjectionNames::ProjectionsStreamPrefix . $this->definition->name() . ProjectionNames::ProjectionCheckpointStreamSuffix;
                yield from $this->acquireLock($lockConnection, $checkpointStream);
                yield from $this->writeCheckPoint($checkpointStream, $state, $streamPositions);
            }

            foreach ($streamsOfEmittedEvents as $stream) {
                yield from $this->releaseLock($lockConnection, $stream);
            }

            if ($stop) {
                $this->loadedState = $state;
                $this->state = ProjectionState::stopped();
                $this->eventsProcessedAfterRestart = 0;
            }

            if ($this->config->checkpointsEnabled()) {
                yield from $this->releaseLock($lockConnection, $checkpointStream);
            }

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

        foreach (\array_chunk($emittedEvents, $this->config->maxWriteBatchLength()) as $batch) {
            $time = \microtime(true);
            $batchSize = \count($batch);

            $this->statisticsRecorder->record(
                $time,
                $batchSize
            );

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
            $sql .= \str_repeat('(?, ?, ?, ?, ?, ?, ?, ?, ?, ?), ', $batchSize);
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
        $this->checkpointStatus = 'Writing';

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

        $this->lastCheckpoint = $streamPositions;

        /** @var CommandResult $result */
        $result = yield $statement->execute($params);

        if (0 === $result->affectedRows()) {
            throw new Exception\RuntimeException('Could not write checkpoint');
        }

        $this->checkpointStatus = '';

        $this->logger->info('Checkpoint created');
    }

    /** @throws Throwable */
    private function determineReader(): Promise
    {
        return call(function (): Generator {
            $sources = $this->processor->sources();

            if (\count($sources->streams()) === 1) {
                $streamName = $sources->streams()[0];

                if (! isset($this->streamPositions[$streamName])) {
                    $this->streamPositions[$streamName] = -1;
                    $this->lastCheckpoint[$streamName] = -1;
                }

                yield $this->checkStream($streamName);

                return yield new Success(new StreamEventReader(
                    $this->readMutex,
                    $this->pool,
                    $this->processingQueue,
                    $this->config->stopOnEof(),
                    $streamName,
                    $this->streamPositions[$streamName]
                ));
            } elseif (\count($sources->streams()) > 2) {
                foreach ($sources->streams() as $streamName) {
                    if (! isset($this->streamPositions[$streamName])) {
                        $this->streamPositions[$streamName] = -1;
                        $this->lastCheckpoint[$streamName] = -1;
                    }

                    yield $this->checkStream($streamName);
                }

                return yield new Success(new StreamsEventReader(
                    $this->readMutex,
                    $this->pool,
                    $this->processingQueue,
                    $this->config->stopOnEof(),
                    $sources->streams(),
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
            $streamRoles = yield from (new LoadProjectionStreamRolesOperation())($this->pool, $streamName);

            $this->checkAcl($streamRoles, $streamName);

            $this->checkedStreams->put($streamName, 'ok');
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

    public function reset(): void
    {
        $this->logger->info('Resetting projection');
        $this->state = ProjectionState::stopping();

        Loop::repeat(1, function (string $watcherId): Generator {
            if ($this->state->equals(ProjectionState::stopping())) {
                return;
            }

            Loop::cancel($watcherId);

            $this->loadedState = null;
            $this->streamPositions = [];
            $this->checkedStreams->clear();

            if ($this->config->checkpointsEnabled()) {
                $this->lastCheckpoint = [];

                $sql = 'DELETE FROM events WHERE stream_name = ?';

                /** @var Statement $statement */
                $statement = yield $this->pool->prepare($sql);

                $checkpointStream = ProjectionNames::ProjectionsStreamPrefix . $this->definition->name() . ProjectionNames::ProjectionCheckpointStreamSuffix;

                yield $statement->execute([$checkpointStream]);
            }

            if ($this->enabled) {
                $this->runQuery();
            }
        });
    }

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
            'emitEnabled' => $this->config->emitEnabled(),
            'checkpointHandledThreshold' => $this->config->checkpointHandledThreshold(),
            'checkpointUnhandledBytesThreshold' => $this->config->checkpointUnhandledBytesThreshold(),
            'pendingEventsThreshold' => $this->config->pendingEventsThreshold(),
            'maxWriteBatchLength' => $this->config->maxWriteBatchLength(),
        ];
    }

    public function getDefinition(): array
    {
        return [
            'name' => $this->definition->name(),
            'query' => $this->definition->query(),
            'definition' => $this->processor->sources()->toArray(),
        ];
    }

    public function getState(): array
    {
        return $this->processor->getState();
    }

    public function getStatistics(): Promise
    {
        return call(function (): Generator {
            $progress = 0;
            $total = 0;

            $head = yield from $this->reader->head();

            foreach ($head as $streamName => $position) {
                $total += $position;
                $progress += $this->streamPositions[$streamName];
            }

            if (! $this->reader || $this->reader->paused()) {
                $readsInProgress = 0;
            } else {
                $readsInProgress = EventReader::MaxReads;
            }

            $stats = [
                'status' => $this->state->name(),
                'stateReason' => $this->stateReason,
                'enabled' => $this->enabled,
                'name' => $this->definition->name(),
                'id' => $this->id,
                'mode' => $this->mode->name(),
                'bufferedEvents' => $this->processingQueue->count(),
                'eventsPerSecond' => $this->statisticsRecorder->eventsPerSecond(),
                'eventsProcessedAfterRestart' => $this->eventsProcessedAfterRestart,
                'readsInProgress' => $readsInProgress,
                'writesInProgress' => $this->currentlyWriting,
                'writeQueue' => \count($this->emittedEvents),
                'checkpointStatus' => $this->checkpointStatus,
                'position' => $this->streamPositions,
                'lastCheckpoint' => $this->lastCheckpoint,
                'progress' => (0 === $total) ? 100.0 : \floor($progress * 100 / $total * 10000) / 10000,
            ];

            return new Success($stats);
        });
    }

    /** @throws Throwable */
    private function createStream(string $streamName): Generator
    {
        if (! isset($this->operations[__FUNCTION__])) {
            $this->operations[__FUNCTION__] = new CreateStreamOperation($this->pool);
        }

        yield from $this->operations[__FUNCTION__]($streamName);
    }

    /** @throws Throwable */
    private function getExpectedVersion(string $streamName): Generator
    {
        if (! isset($this->operations[__FUNCTION__])) {
            $this->operations[__FUNCTION__] = new GetExpectedVersionOperation($this->pool);
        }

        return yield from $this->operations[__FUNCTION__]($streamName);
    }

    private function checkAcl(array $streamRoles, string $streamName): void
    {
        if ('$system' !== $this->config->runAs()->identity()
            && ! \in_array($this->config->runAs()->identity(), $streamRoles)
            && empty(\array_intersect($this->config->runAs()->roles(), $streamRoles))
        ) {
            throw Exception\AccessDenied::toStream($streamName);
        }
    }
}
