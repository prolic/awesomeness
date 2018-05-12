<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager;

use Amp\Coroutine;
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
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\Projections\ProjectionEventTypes;
use Prooph\EventStore\Projections\ProjectionNames;
use Prooph\EventStore\Projections\ProjectionState;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\SystemSettings;
use Prooph\PdoEventStore\Internal\StreamOperation;
use Prooph\PostgresProjectionManager\Internal\Exception\StreamNotFound;
use Psr\Log\LoggerInterface as PsrLogger;
use Ramsey\Uuid\Uuid;
use SplQueue;
use Throwable;
use function array_intersect;
use function current;
use function explode;
use function floor;
use function in_array;
use function is_array;
use function is_callable;
use function is_string;
use function microtime;

/** @internal  */
class Projector
{
    /** @var SplQueue */
    private $queue;
    /** @var Pool */
    private $pool;
    /** @var string */
    private $name;
    /** @var string */
    private $id;
    /** @var PsrLogger */
    private $logger;
    /** @var SystemSettings */
    private $settings;
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
    private $pendingEventsThreshold = 5000;
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

    public function __construct(
        Pool $pool,
        string $name,
        string $id,
        PsrLogger $logger,
        SystemSettings $settings
    ) {
        $this->pool = $pool;
        $this->name = $name;
        $this->id = $id;
        $this->logger = $logger;
        $this->projectionState = ProjectionState::stopped();
        $this->settings = $settings;
        $this->knownStreamIds = new LRUCache(1000);
    }

    /** @throws Throwable */
    public function tryStart(): Promise
    {
        return new Coroutine($this->doTryStart());
    }

    /** @throws Throwable */
    private function doTryStart(): Generator
    {
        yield new Coroutine($this->doLoad());

        if ($this->enabled) {
            yield $this->start();
        }
    }

    /** @throws Throwable */
    private function doLoad(): Generator
    {
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
            $streamId = $this->knownStreamIds->get($checkpointStream) ?? yield $this->determineStreamId($checkpointStream);

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
                $streamPositions = json_decode($data, true);

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

        return new Coroutine($this->doStart());
    }

    /** @throws Throwable */
    private function doStart(): Generator
    {
        $this->enabled = true;
        $this->state = ProjectionState::initial();

        Loop::repeat(100, function (string $watcherId) {
            if ($this->projectionState->equals(ProjectionState::stopping())) {
                Loop::cancel($watcherId);
                $this->projectionState = ProjectionState::stopped();
                $this->logger->debug('Projection "' . $this->name . '" stopped');
            }
        });

        $this->lastCheckPointMs = microtime(true) * 10000;

        yield from $this->runQuery();

        $this->projectionState = ProjectionState::running();
        $this->logger->debug('Started projection "' . $this->name .'"');

        return yield new Success();
    }

    public function emit(string $stream, string $eventType, string $data, string $metadata): Promise
    {
        return new Coroutine($this->doEmit($stream, $eventType, $data, $metadata));
    }

    private function doEmit(string $stream, string $eventType, string $data, string $metadata): Generator
    {
        $isJson = false;
        json_decode($data);

        if (0 === json_last_error()) {
            $isJson = true;
        }

        $connection = yield $this->pool->extractConnection();

        yield new Coroutine($this->acquireLock($connection, $stream));

        $expectedVersion = ExpectedVersion::NoStream;

        try {
            $streamId = yield $this->determineStreamId($stream);

            /** @var Statement $statement */
            $statement = yield $connection->prepare(<<<SQL
SELECT MAX(event_number) as current_version FROM events WHERE stream_id = ?
SQL
            );
            /** @var ResultSet $result */
            $result = yield $statement->execute([$streamId]);

            while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
                $data = $result->getCurrent();
                $expectedVersion = $data->current_version;
            }
        } catch (StreamNotFound $e) {
            $streamId = Uuid::uuid4()->toString();
            /** @var Statement $statement */
            $statement = yield $connection->prepare(<<<SQL
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
        $statement = yield $connection->prepare($sql);
        /** @var CommandResult $result */
        $result = yield $statement->execute($params);

        if (0 === $result->affectedRows()) {
            throw new Exception\RuntimeException('Could not emit to stream ' . $streamId);
        }

        yield new Coroutine($this->releaseLock($connection, $stream));
    }

    public function linkTo(string $stream, RecordedEvent $event, string $metadata = ''): Promise
    {
        //@todo implement
    }

    private function when(array $handlers): Projector
    {
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
    public function stop(): Promise
    {
        if (! $this->projectionState->equals(ProjectionState::running())) {
            return new Success();
        }

        $this->logger->debug('Stopping projection "' . $this->name . '"');
        $this->enabled = false;
        $this->projectionState = ProjectionState::stopping();

        return new Success();
    }

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
    private function runQuery(): Generator
    {
        eval($this->query);

        /** @var EventReader $reader */
        $reader = yield $this->determineReader();
        yield $reader->requestEvents();

        $paused = false;

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

        while ($this->enabled) {
            if (! $this->queue->isEmpty()) {
                /** @var RecordedEvent $event */
                $event = $this->queue->dequeue();
                yield $this->handle($event);
            }

            if ($this->queue->count() > $this->pendingEventsThreshold) {
                $reader->pause();
                $paused = true;
            } elseif ($paused) {
                yield $reader->requestEvents();
            }
        }

        if (! $paused) {
            $reader->pause();

            while (! $this->queue->isEmpty()) {
                /** @var RecordedEvent $event */
                $event = $this->queue->dequeue();
                yield $this->handle($event);
            }
        }
    }

    /** @throws Throwable */
    private function handle(RecordedEvent $event): Promise
    {
        $handle = static function (callable $handler, RecordedEvent $event): void {
            $state = $handler($this->state, $event);

            if (is_array($state)) {
                $this->state = $state;
            }
        };

        $handled = false;

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

        if ($this->currentBatchSize > $this->checkpointHandledThreshold) {
            if (0 === $this->checkpointAfterMs
                || (floor(microtime(true) * 1000 - $this->lastCheckPointMs) > $this->checkpointAfterMs)
            ) {
                return new Coroutine($this->writeCheckPoint());
            }
        }

        return new Success();
    }

    /** @throws Throwable */
    private function writeCheckPoint(): Generator
    {
        $checkpointStream = ProjectionNames::ProjectionsStreamPrefix . $this->name . ProjectionNames::ProjectionCheckpointStreamSuffix;

        /** @var Connection $connection */
        $connection = yield $this->pool->extractConnection();

        try {
            $streamId = $this->knownStreamIds->get($checkpointStream) ?? yield $this->determineStreamId($checkpointStream);
        } catch (StreamNotFound $e) {
            $streamId = Uuid::uuid4()->toString();
            /** @var Statement $statement */
            $statement = yield $connection->prepare(<<<SQL
INSERT INTO streams (stream_id, stream_name, mark_deleted, deleted) VALUES (?, ?, ?, ?);
SQL
            );
            /** @var CommandResult $result */
            $result = yield $statement->execute([$streamId, $checkpointStream, 0, 0]);

            if (0 === $result->affectedRows()) {
                throw new Exception\RuntimeException('Could not create stream id for ' . $checkpointStream);
            }
        }

        yield new Coroutine($this->acquireLock($connection, $checkpointStream));

        /** @var Statement $statement */
        $statement = yield $connection->prepare(<<<SQL
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
        $statement = yield $connection->prepare($sql);
        /** @var CommandResult $result */
        $result = yield $statement->execute($params);

        if (0 === $result->affectedRows()) {
            throw new Exception\RuntimeException('Could not write checkpoint for ' . $this->name);
        }

        $this->lastCheckPointMs = microtime(true) * 10000;

        yield new Coroutine($this->releaseLock($connection, $checkpointStream));
    }

    private function fromStream(string $streamName): Projector
    {
        $this->evaledQuery = ['streams' => [$streamName]];

        return $this;
    }

    private function fromStreams(string ...$streamNames): Projector
    {
        $this->evaledQuery = ['streams' => $streamNames];

        return $this;
    }

    private function fromCategory(string $name): Projector
    {
        $this->evaledQuery = ['categories' => [$name]];

        return $this;
    }

    private function fromCategories(string ...$names): Projector
    {
        $this->evaledQuery = ['categories' => $names];

        return $this;
    }

    private function fromAll(): Projector
    {
        $this->evaledQuery = ['all' => true];

        return $this;
    }

    /** @throws Throwable */
    private function determineReader(): Promise
    {
        return new Coroutine($this->doDetermineReader());
    }

    /** @throws Throwable */
    private function doDetermineReader(): Generator
    {
        if (isset($this->evaledQuery['streams']) && 1 === count($this->evaledQuery['streams'])) {
            $streamName = current($this->evaledQuery['streams']);

            if (! isset($this->streamPositions[$streamName])) {
                $this->streamPositions[$streamName] = 0;
            }

            $streamId = $this->knownStreamIds->get($streamName) ?? yield $this->determineStreamId($streamName);

            return yield new Success(new StreamEventReader(
                $this->pool,
                new SplQueueEventPublisher($this->queue),
                'Continuous' !== $this->mode,
                $streamName,
                $streamId,
                $this->streamPositions[$streamName]
            ));
        }

        // @todo implement
        throw new Exception\RuntimeException('Not implemented');
    }

    /** @throws Throwable */
    private function determineStreamId(string $streamName): Promise
    {
        return new Coroutine($this->doDetermineStreamId($streamName));
    }

    /** @throws Throwable */
    private function doDetermineStreamId(string $streamName): Generator
    {
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

            return $data->stream_id;
        }

        throw StreamNotFound::with($streamName);
    }

    private function acquireLock(Connection $connection, string $name): Generator
    {
        /** @var Statement $statement */
        $statement = yield $connection->prepare('SELECT PG_ADVISORY_LOCK(HASHTEXT(?)) as stream_lock;');
        /** @var ResultSet $result */
        $result = yield $statement->execute([$name]);
        yield $result->advance(ResultSet::FETCH_OBJECT);
    }

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

    private function createHandlerContext(): object
    {
        return new class($this) {
            private $projector;

            public function __construct(Projector $projector)
            {
                $this->projector = $projector;
            }

            public function stop(): void
            {
                $this->projector->stop();
            }

            public function linkTo(string $streamName, RecordedEvent $event, string $metadata = ''): void
            {
                $this->projector->linkTo($streamName, $event, $metadata);
            }

            public function emit(string $streamName, string $eventType, string $data, string $metadata = ''): void
            {
                $this->projector->emit($streamName, $eventType, $data, $metadata);
            }
        };
    }
}
