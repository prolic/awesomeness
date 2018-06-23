<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager;

use Amp\Deferred;
use Amp\Loop;
use Amp\Postgres\CommandResult;
use Amp\Postgres\Connection;
use Amp\Postgres\Pool;
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
use Prooph\EventStore\ProjectionManagement\Internal\ProjectionConfig as InternalProjectionConfig;
use Prooph\EventStore\ProjectionManagement\ProjectionConfig;
use Prooph\EventStore\ProjectionManagement\ProjectionDefinition;
use Prooph\EventStore\Projections\ProjectionMode;
use Prooph\EventStore\Projections\ProjectionNames;
use Prooph\EventStore\Projections\ProjectionState;
use Prooph\EventStore\Projections\StandardProjections;
use Prooph\EventStore\RecordedEvent;
use Prooph\PostgresProjectionManager\Exception\QueryEvaluationError;
use Prooph\PostgresProjectionManager\Exception\StreamNotFound;
use Prooph\PostgresProjectionManager\Operations\CreateStreamOperation;
use Prooph\PostgresProjectionManager\Operations\DeleteProjectionOperation;
use Prooph\PostgresProjectionManager\Operations\GetExpectedVersionOperation;
use Prooph\PostgresProjectionManager\Operations\LoadConfigOperation;
use Prooph\PostgresProjectionManager\Operations\LoadConfigResult;
use Prooph\PostgresProjectionManager\Operations\LoadLatestCheckpointOperation;
use Prooph\PostgresProjectionManager\Operations\LoadLatestCheckpointResult;
use Prooph\PostgresProjectionManager\Operations\LoadProjectionStreamRolesOperation;
use Prooph\PostgresProjectionManager\Operations\LoadTrackedEmittedStreamsOperation;
use Prooph\PostgresProjectionManager\Operations\LockOperation;
use Prooph\PostgresProjectionManager\Operations\UpdateProjectionOperation;
use Prooph\PostgresProjectionManager\Operations\WriteCheckPointOperation;
use Prooph\PostgresProjectionManager\Operations\WriteEmittedStreamsOperation;
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
    /** @var InternalProjectionConfig */
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
    private $persistMutex;
    /** @var int */
    private $projectionEventNumber;
    /** @var int */
    private $lastCheckpointMs;
    /** @var LRUCache */
    private $checkedStreams;
    /** @var string[] */
    private $streamsOfEmittedEvents = [];
    /** @var array */
    private $emittedEvents = [];
    /** @var string[] */
    private $trackedEmittedStreams = [];
    /** @var string[] */
    private $unhandledTrackedEmittedStreams = [];
    /** @var int */
    private $eventsProcessedAfterRestart = 0;
    /** @var int */
    private $currentBatchSize = 0;
    /** @var int */
    private $currentUnhandledBytes = 0;
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
    /** @var CheckpointTag|null */
    private $lastCheckpoint;

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

            return $this->shutdownPromise;
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
                $this->lastCheckpoint = $latestCheckpoint->checkpointTag();
            }

            $this->checkpointStatus = '';
        }

        if ($this->config->trackEmittedStreams()) {
            $emittedStream = ProjectionNames::ProjectionsStreamPrefix . $this->definition->name() . ProjectionNames::ProjectionEmittedStreamSuffix;
            $this->trackedEmittedStreams = yield from (new LoadTrackedEmittedStreamsOperation())($this->pool, $emittedStream);
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

        if (null !== $enableRunAs) {
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

    public function shutdown(): void
    {
        if ($this->state->equals(ProjectionState::stopped())) {
            $this->logger->info('shutdown done');
            $this->shutdownDeferred->resolve(0);
        }

        if ($this->state->equals(ProjectionState::initial())) {
            $this->state = ProjectionState::stopped();
            $this->logger->info('shutdown done');
            $this->shutdownDeferred->resolve(0);
        }

        $this->state = ProjectionState::stopping();

        Loop::repeat(100, function (string $watcherId): void {
            if ($this->state->equals(ProjectionState::stopped())) {
                Loop::cancel($watcherId);
                $this->logger->info('shutdown done');
                $this->shutdownDeferred->resolve(0);
            } else {
                $this->logger->debug('still waiting for shutdown...');
            }
        });
    }

    private function runQuery(): void
    {
        $this->logger->debug('Running projection');

        $this->state = ProjectionState::running();

        $this->reader->run();

        $this->lastCheckpointMs = \microtime(true) * 10000;

        $readingTask = function () use (&$readingTask): void {
            if ($this->reader->eof()
                && $this->processingQueue->isEmpty()
            ) {
                $this->persist(true);

                return;
            }

            if ($this->state->equals(ProjectionState::stopping()) && ! $this->reader->paused()) {
                $this->reader->pause();
                Loop::defer($readingTask);

                return;
            }

            if ($this->processingQueue->isEmpty()
                && $this->reader->paused()
                && $this->state->equals(ProjectionState::stopping())
            ) {
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

            ++$this->currentBatchSize;
            ++$this->eventsProcessedAfterRestart;

            if (\count($this->emittedEvents) >= $this->config->maxWriteBatchLength()
                || (
                    ($this->currentBatchSize >= $this->config->checkpointHandledThreshold()
                        || $this->currentUnhandledBytes >= $this->config->checkpointUnhandledBytesThreshold()
                    )
                    && (
                        \floor(\microtime(true) * 10000 - $this->lastCheckpointMs) >= $this->config->checkpointAfterMs()
                    )
                )
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
        $unhandledTrackedEmittedStreams = $this->unhandledTrackedEmittedStreams;
        $checkpointTag = $this->reader->checkpointTag();

        $this->emittedEvents = [];
        $this->streamsOfEmittedEvents = [];
        $this->unhandledTrackedEmittedStreams = [];

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
        $this->currentUnhandledBytes = 0;
        $this->lastCheckpointMs = \microtime(true) * 10000;

        Loop::defer(function () use (
            $emittedEvents,
            $streamsOfEmittedEvents,
            $state,
            $checkpointTag,
            $unhandledTrackedEmittedStreams,
            $stop
        ): Generator {
            /** @var Lock $lock */
            $lock = yield $this->persistMutex->acquire();

            foreach ($streamsOfEmittedEvents as $stream) {
                yield from $this->acquireLock($stream);
            }

            if ($this->config->trackEmittedStreams()) {
                $emittedStream = ProjectionNames::ProjectionsStreamPrefix . $this->definition->name() . ProjectionNames::ProjectionEmittedStreamSuffix;

                yield from $this->acquireLock($emittedStream);

                $emittedStreamsExpectedVersion = ExpectedVersion::NoStream;

                try {
                    yield $this->checkStream($emittedStream);
                    $emittedStreamsExpectedVersion = yield from $this->getExpectedVersion($emittedStream);
                } catch (StreamNotFound $e) {
                    yield from $this->createStream($emittedStream);
                }

                if (! isset($this->operations['writeEmittedStreams'])) {
                    $this->operations['writeEmittedStreams'] = new WriteEmittedStreamsOperation($this->pool);
                }

                $this->operations['writeEmittedStreams'](
                    $emittedStream,
                    $unhandledTrackedEmittedStreams,
                    $emittedStreamsExpectedVersion
                );

                foreach ($unhandledTrackedEmittedStreams as $trackedEmittedStream) {
                    $this->trackedEmittedStreams[] = $trackedEmittedStream;
                }

                yield from $this->releaseLock($emittedStream);
            }

            yield from $this->writeEmittedEvents($emittedEvents, $streamsOfEmittedEvents);

            if ($this->config->checkpointsEnabled()) {
                $checkpointStream = ProjectionNames::ProjectionsStreamPrefix . $this->definition->name() . ProjectionNames::ProjectionCheckpointStreamSuffix;
                yield from $this->acquireLock($checkpointStream);
                yield from $this->writeCheckPoint($checkpointStream, $state, $checkpointTag);
            }

            foreach ($streamsOfEmittedEvents as $stream) {
                yield from $this->releaseLock($stream);
            }

            if ($stop) {
                $this->loadedState = $state;
                $this->state = ProjectionState::stopped();
                $this->eventsProcessedAfterRestart = 0;
            }

            if ($this->config->checkpointsEnabled()) {
                yield from $this->releaseLock($checkpointStream);
            }

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

        $time = \microtime(true);
        $batchSize = \count($emittedEvents);

        $this->statisticsRecorder->record(
            $time,
            $batchSize
        );

        $params = [];
        foreach ($emittedEvents as $record) {
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

    /** @throws Throwable */
    private function writeCheckPoint(string $checkpointStream, array $state, CheckpointTag $checkpointTag): Generator
    {
        $this->checkpointStatus = 'Writing';

        try {
            yield $this->checkStream($checkpointStream);
        } catch (StreamNotFound $e) {
            yield from $this->createStream($checkpointStream);
        }

        $expectedVersion = yield from $this->getExpectedVersion($checkpointStream);

        if (! isset($this->operations[__FUNCTION__])) {
            $this->operations[__FUNCTION__] = new WriteCheckPointOperation($this->pool);
        }

        $this->lastCheckpoint = $checkpointTag;
        yield from $this->operations[__FUNCTION__]($checkpointStream, $expectedVersion, $state, $checkpointTag);
        $this->checkpointStatus = '';

        $this->logger->info('Checkpoint created');
    }

    /** @throws Throwable */
    private function determineReader(): Promise
    {
        return call(function (): Generator {
            $sources = $this->processor->sources();

            $builder = new EventReaderBuilder(
                $sources,
                $this->lastCheckpoint,
                function (string $streamName): Promise {
                    return $this->checkStream($streamName);
                },
                $this->pool,
                $this->processingQueue,
                $this->config->stopOnEof()
            );

            return yield from $builder->buildEventReader();
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
    private function acquireLock(string $streamName): Generator
    {
        if (! isset($this->operations['lockOperation'])) {
            $this->operations['lockOperation'] = new LockOperation($this->pool);
        }

        yield from $this->operations['lockOperation']->acquire($streamName);
    }

    /** @throws Throwable */
    private function releaseLock(string $streamName): Generator
    {
        if (! isset($this->operations['lockOperation'])) {
            $this->operations['lockOperation'] = new LockOperation($this->pool);
        }

        yield from $this->operations['lockOperation']->release($streamName);
    }

    /** @throws Throwable */
    private function emit(string $stream, string $eventType, string $data, string $metadata, bool $isJson = false): void
    {
        if (! \in_array($stream, $this->streamsOfEmittedEvents, true)) {
            $this->streamsOfEmittedEvents[] = $stream;
        }

        if ($this->config->trackEmittedStreams()
            && (! \in_array($stream, $this->trackedEmittedStreams, true)
                || ! \in_array($stream, $this->unhandledTrackedEmittedStreams, true)
            )
        ) {
            $this->unhandledTrackedEmittedStreams[] = $stream;
        }

        $this->currentUnhandledBytes += \strlen($stream) + \strlen($eventType) + \strlen($data) + \strlen($metadata);

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

            $this->currentUnhandledBytes += \strlen($linkTo[1]);
        }

        $this->emittedEvents[] = $eventData;
    }

    public function reset(string $enableRunAs = null): void
    {
        $this->logger->info('Resetting projection');
        $this->state = ProjectionState::stopping();

        Loop::repeat(1, function (string $watcherId) use ($enableRunAs): Generator {
            if ($this->state->equals(ProjectionState::stopping())) {
                return;
            }

            Loop::cancel($watcherId);

            $streamsToDelete = $this->trackedEmittedStreams;
            $streamsToDelete[] = ProjectionNames::ProjectionsStreamPrefix . $this->definition->name() . ProjectionNames::ProjectionCheckpointStreamSuffix;
            $streamsToDelete[] = ProjectionNames::ProjectionsStreamPrefix . $this->definition->name() . ProjectionNames::ProjectionEmittedStreamSuffix;
            $streamsToDelete[] = ProjectionNames::ProjectionsStreamPrefix . $this->definition->name() . ProjectionNames::ProjectionsStateStreamSuffix;

            $operation = new DeleteProjectionOperation($this->pool);
            yield from $operation($this->definition->name(), $streamsToDelete, true);

            $this->loadedState = null;
            $this->checkedStreams->clear();
            $this->lastCheckpoint = null;
            $this->trackedEmittedStreams = [];
            $this->unhandledTrackedEmittedStreams = [];
            $this->reader = yield $this->determineReader();

            if (null !== $enableRunAs) {
                $this->config->runAs()->setIdentity($enableRunAs);
            }

            if ($this->enabled) {
                $this->runQuery();
            }
        });
    }

    public function delete(
        bool $deleteStateStream,
        bool $deleteCheckpointStream,
        bool $deleteEmittedStreams
    ): void {
        $this->logger->info('Deleting projection');
        $this->state = ProjectionState::stopping();

        Loop::repeat(1, function (string $watcherId) use (
            $deleteStateStream,
            $deleteCheckpointStream,
            $deleteEmittedStreams
        ): Generator {
            if ($this->state->equals(ProjectionState::stopping())) {
                return;
            }

            Loop::cancel($watcherId);

            $streamsToDelete = [];

            if ($deleteEmittedStreams) {
                $streamsToDelete = $this->trackedEmittedStreams;
            }

            if ($deleteCheckpointStream) {
                $streamsToDelete[] = ProjectionNames::ProjectionsStreamPrefix . $this->definition->name() . ProjectionNames::ProjectionCheckpointStreamSuffix;
            }

            if ($deleteStateStream) {
                $streamsToDelete[] = ProjectionNames::ProjectionsStreamPrefix . $this->definition->name() . ProjectionNames::ProjectionsStateStreamSuffix;
            }

            $streamsToDelete[] = ProjectionNames::ProjectionsStreamPrefix . $this->definition->name() . ProjectionNames::ProjectionEmittedStreamSuffix;
            $streamsToDelete[] = ProjectionNames::ProjectionsStreamPrefix . $this->definition->name();

            $operation = new DeleteProjectionOperation($this->pool);
            yield from $operation($this->definition->name(), $streamsToDelete, false);

            $this->loadedState = null;
            $this->checkedStreams->clear();
            $this->lastCheckpoint = null;
            $this->trackedEmittedStreams = [];
            $this->unhandledTrackedEmittedStreams = [];

            $this->shutdownDeferred->resolve(0);
        });
    }

    public function updateConfig(ProjectionConfig $config): Promise
    {
        if (StandardProjections::isStandardProjection($this->definition->name())) {
            throw new Exception\ProjectionException('Cannot override standard projections');
        }

        if ($this->state->equals(ProjectionState::running())) {
            throw new Exception\ProjectionException('Cannot update query while running');
        }

        return call(function () use ($config): Generator {
            $operation = new UpdateProjectionOperation($this->pool);

            $config = yield from $operation(
                $this->id,
                $this->definition->name(),
                $config,
                $this->definition->query(),
                $this->config->emitEnabled(),
                [
                    'handlerType' => 'PHP',
                    'runAs' => $this->config->runAs()->toArray(),
                    'mode' => $this->mode->name(),
                    'emitEnabled' => $this->config->emitEnabled(),
                    'checkpointsEnabled' => $this->config->checkpointsEnabled(),
                    'trackEmittedStreams' => $this->config->trackEmittedStreams(),
                    'checkpointAfterMs' => $this->config->checkpointAfterMs(),
                    'checkpointHandledThreshold' => $this->config->checkpointHandledThreshold(),
                    'checkpointUnhandledBytesThreshold' => $this->config->checkpointUnhandledBytesThreshold(),
                    'pendingEventsThreshold' => $this->config->pendingEventsThreshold(),
                    'maxWriteBatchLength' => $this->config->maxWriteBatchLength(),
                    'query' => $this->definition->query(),
                    'enabled' => $this->enabled,
                ]
            );

            $this->config = $config;
        });
    }

    public function updateQuery(string $type, string $query, bool $emitEnabled): Promise
    {
        if (StandardProjections::isStandardProjection($this->definition->name())) {
            throw new Exception\ProjectionException('Cannot override standard projections');
        }

        if ($this->state->equals(ProjectionState::running())) {
            throw new Exception\ProjectionException('Cannot update query while running');
        }

        if ($type !== 'PHP') {
            throw new Exception\ProjectionException('Only projection type support for now is \'PHP\'');
        }

        return call(function () use ($type, $query, $emitEnabled): Generator {
            $operation = new UpdateProjectionOperation($this->pool);

            yield from $operation(
                $this->id,
                $this->definition->name(),
                null,
                $query,
                $emitEnabled,
                [
                    'handlerType' => 'PHP',
                    'runAs' => $this->config->runAs()->toArray(),
                    'mode' => $this->mode->name(),
                    'emitEnabled' => $this->config->emitEnabled(),
                    'checkpointsEnabled' => $this->config->checkpointsEnabled(),
                    'trackEmittedStreams' => $this->config->trackEmittedStreams(),
                    'checkpointAfterMs' => $this->config->checkpointAfterMs(),
                    'checkpointHandledThreshold' => $this->config->checkpointHandledThreshold(),
                    'checkpointUnhandledBytesThreshold' => $this->config->checkpointUnhandledBytesThreshold(),
                    'pendingEventsThreshold' => $this->config->pendingEventsThreshold(),
                    'maxWriteBatchLength' => $this->config->maxWriteBatchLength(),
                    'query' => $this->definition->query(),
                    'enabled' => $this->enabled,
                ]
            );

            $sources = $this->processor->sources();
            $this->definition = new ProjectionDefinition(
                $this->definition->name(),
                $query,
                $emitEnabled,
                $sources,
                []
            );
        });
    }

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
                $progress += $this->reader->checkpointTag()->streamPosition($streamName);
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
                'position' => $this->reader->checkpointTag()->toArray(),
                'lastCheckpoint' => $this->lastCheckpoint->toArray(),
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
