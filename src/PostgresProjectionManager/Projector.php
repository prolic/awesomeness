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
ORDER BY e1.event_number DESC
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

    private function when(array $handlers): Projector
    {
        foreach ($handlers as $name => $callback) {
            if (! is_string($name) || ! is_callable($callback)) {
                throw new Exception\RuntimeException('Invalid argument passed to when()');
            }

            // @todo check this
            //$this->handlers[$name] = Closure::bind($callback, $this->createHandlerContext($this->currentStreamName));
        }

        $this->handlers = $handlers;

        return $this;
    }

    /*
        public function emit(string $stream, string $eventType, string $eventBody, string $metadata): void
        {
            $isJson = false;
            json_decode($eventBody);

            if (0 === json_last_error()) {
                $isJson = true;
            }

            $this->eventStoreConnection->appendToStream(
                $stream,
                ExpectedVersion::Any,
                [
                    new EventData(
                        EventId::generate(),
                        $eventType,
                        $isJson,
                        $eventBody,
                        $metadata
                    ),
                ]
            );
        }

        public function linkTo(string $stream, RecordedEvent $event, string $metadata = ''): void
        {
            $this->eventStoreConnection->appendToStream(
                $stream,
                ExpectedVersion::Any,
                [
                    // @todo link to real event
                    new EventData(
                        EventId::generate(),
                        SystemEventTypes::LinkTo,
                        false,
                        $event->eventNumber() . '@' . $event->streamId(),
                        $metadata
                    ),
                ]
            );
        }

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

        public function run(bool $keepRunning = true): void
        {
            if (null === $this->query
                || (null === $this->handler && empty($this->handlers))
            ) {
                throw new Exception\RuntimeException('No handlers configured');
            }
            switch ($this->fetchRemoteStatus()) {
                case ProjectionStatus::STOPPING():
                    $this->stop();

                    return;
                case ProjectionStatus::DELETING():
                    $this->delete(false);

                    return;
                case ProjectionStatus::DELETING_INCL_EMITTED_EVENTS():
                    $this->delete(true);

                    return;
                case ProjectionStatus::RESETTING():
                    $this->reset();
                    break;
                default:
                    break;
            }
            $this->createProjection();
            $this->acquireLock();
            $this->prepareStreamPositions();
            $this->load();
            $singleHandler = null !== $this->handler;
            $this->isStopped = false;
            try {
                do {
                    foreach ($this->streamPositions as $streamName => $position) {
                        try {
                            $streamEvents = $this->eventStoreConnection->load(new StreamName($streamName), $position + 1);
                        } catch (Exception\StreamNotFound $e) {
                            // ignore
                            continue;
                        }
                        if ($singleHandler) {
                            $this->handleStreamWithSingleHandler($streamName, $streamEvents);
                        } else {
                            $this->handleStreamWithHandlers($streamName, $streamEvents);
                        }
                        if ($this->isStopped) {
                            break;
                        }
                    }
                    if (0 === $this->eventCounter) {
                        usleep($this->sleep);
                        $this->updateLock();
                    } else {
                        $this->persist();
                    }
                    $this->eventCounter = 0;

                    switch ($this->fetchRemoteStatus()) {
                        case ProjectionStatus::STOPPING():
                            $this->stop();
                            break;
                        case ProjectionStatus::DELETING():
                            $this->delete(false);
                            break;
                        case ProjectionStatus::DELETING_INCL_EMITTED_EVENTS():
                            $this->delete(true);
                            break;
                        case ProjectionStatus::RESETTING():
                            $this->reset();
                            break;
                        default:
                            break;
                    }
                    $this->prepareStreamPositions();
                } while ($keepRunning && ! $this->isStopped);
            } finally {
                $this->releaseLock();
            }
        }

        private function fetchRemoteStatus(): ProjectionStatus
        {
            $projectionsTable = $this->quoteTableName($this->projectionsTable);
            $sql = <<<EOT
    SELECT status FROM $projectionsTable WHERE name = ? LIMIT 1;
    EOT;
            $statement = $this->connection->prepare($sql);
            try {
                $statement->execute([$this->name]);
            } catch (PDOException $exception) {
                // ignore and check error code
            }
            if ($statement->errorCode() !== '00000') {
                $errorCode = $statement->errorCode();
                $errorInfo = $statement->errorInfo()[2];
                throw new RuntimeException(
                    "Error $errorCode. Maybe the projection table is not setup?\nError-Info: $errorInfo"
                );
            }
            $result = $statement->fetch(PDO::FETCH_OBJ);
            if (false === $result) {
                return ProjectionStatus::RUNNING();
            }

            return ProjectionStatus::byValue($result->status);
        }

        private function handleStreamWithSingleHandler(string $streamName, Iterator $events): void
        {
            $this->currentStreamName = $streamName;
            $handler = $this->handler;
            foreach ($events as $key => $event) {
                $this->streamPositions[$streamName] = $key;
                $this->eventCounter++;
                $result = $handler($this->state, $event);
                if (is_array($result)) {
                    $this->state = $result;
                }
                if ($this->eventCounter === $this->persistBlockSize) {
                    $this->persist();
                    $this->eventCounter = 0;
                }
                if ($this->isStopped) {
                    break;
                }
            }
        }

        private function handleStreamWithHandlers(string $streamName, Iterator $events): void
        {
            $this->currentStreamName = $streamName;
            foreach ($events as $key => $event) {
                $this->streamPositions[$streamName] = $key;
                if (! isset($this->handlers[$event->messageName()])) {
                    continue;
                }
                $this->eventCounter++;
                $handler = $this->handlers[$event->messageName()];
                $result = $handler($this->state, $event);
                if (is_array($result)) {
                    $this->state = $result;
                }
                if ($this->eventCounter === $this->persistBlockSize) {
                    $this->persist();
                    $this->eventCounter = 0;
                }
                if ($this->isStopped) {
                    break;
                }
            }
        }

        private function createHandlerContext(?string &$streamName)
        {
            return new class($this, $streamName) {
                private $projector;
                private $streamName;

                public function __construct(Projector $projector, ?string &$streamName)
                {
                    $this->projector = $projector;
                    $this->streamName = &$streamName;
                }

                public function stop(): void
                {
                    $this->projector->stop();
                }

                public function linkTo(string $streamName, Message $event): void
                {
                    $this->projector->linkTo($streamName, $event);
                }

                public function emit(Message $event): void
                {
                    $this->projector->emit($event);
                }

                public function streamName(): ?string
                {
                    return $this->streamName;
                }
            };
        }

        private function load(): void
        {
            $projectionsTable = $this->quoteTableName($this->projectionsTable);
            $sql = <<<EOT
    SELECT position, state FROM $projectionsTable WHERE name = ? LIMIT 1;
    EOT;
            $statement = $this->connection->prepare($sql);
            try {
                $statement->execute([$this->name]);
            } catch (PDOException $exception) {
                // ignore and check error code
            }
            if ($statement->errorCode() !== '00000') {
                throw RuntimeException::fromStatementErrorInfo($statement->errorInfo());
            }
            $result = $statement->fetch(PDO::FETCH_OBJ);
            $this->streamPositions = array_merge($this->streamPositions, json_decode($result->position, true));
            $state = json_decode($result->state, true);
            if (! empty($state)) {
                $this->state = $state;
            }
        }

        private function createProjection(): void
        {
            $projectionsTable = $this->quoteTableName($this->projectionsTable);
            $sql = <<<EOT
    INSERT INTO $projectionsTable (name, position, state, status, locked_until)
    VALUES (?, '{}', '{}', ?, NULL);
    EOT;
            $statement = $this->connection->prepare($sql);
            try {
                $statement->execute([$this->name, $this->status->getValue()]);
            } catch (PDOException $exception) {
                // ignore and check error code
            }
            if ($statement->errorCode() !== '00000') {
                // we ignore duplicate projection errors
                $driver = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
                if (! isset(self::UNIQUE_VIOLATION_ERROR_CODES[$driver]) || self::UNIQUE_VIOLATION_ERROR_CODES[$driver] !== $statement->errorCode()) {
                    throw ProjectionNotCreatedException::with($this->name);
                }
            }
        }

        private function persist(): void
        {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $lockUntilString = $this->createLockUntilString($now);
            $projectionsTable = $this->quoteTableName($this->projectionsTable);
            $sql = <<<EOT
    UPDATE $projectionsTable SET position = ?, state = ?, locked_until = ?
    WHERE name = ?
    EOT;
            $statement = $this->connection->prepare($sql);
            try {
                $statement->execute([
                    json_encode($this->streamPositions),
                    json_encode($this->state),
                    $lockUntilString,
                    $this->name,
                ]);
            } catch (PDOException $exception) {
                // ignore and check error code
            }
            if ($statement->errorCode() !== '00000') {
                throw RuntimeException::fromStatementErrorInfo($statement->errorInfo());
            }
        }

        private function prepareStreamPositions(): void
        {
            $streamPositions = [];
            if (isset($this->query['all'])) {
                $eventStreamsTable = $this->quoteTableName($this->eventStreamsTable);
                $sql = <<<EOT
    SELECT real_stream_name FROM $eventStreamsTable WHERE real_stream_name NOT LIKE '$%';
    EOT;
                $statement = $this->connection->prepare($sql);
                try {
                    $statement->execute();
                } catch (PDOException $exception) {
                    // ignore and check error code
                }
                if ($statement->errorCode() !== '00000') {
                    throw RuntimeException::fromStatementErrorInfo($statement->errorInfo());
                }
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                    $streamPositions[$row->real_stream_name] = 0;
                }
                $this->streamPositions = array_merge($streamPositions, $this->streamPositions);

                return;
            }
            if (isset($this->query['categories'])) {
                $rowPlaces = implode(', ', array_fill(0, count($this->query['categories']), '?'));
                $eventStreamsTable = $this->quoteTableName($this->eventStreamsTable);
                $sql = <<<EOT
    SELECT real_stream_name FROM $eventStreamsTable WHERE category IN ($rowPlaces);
    EOT;
                $statement = $this->connection->prepare($sql);
                try {
                    $statement->execute($this->query['categories']);
                } catch (PDOException $exception) {
                    // ignore and check error code
                }
                if ($statement->errorCode() !== '00000') {
                    throw RuntimeException::fromStatementErrorInfo($statement->errorInfo());
                }
                while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
                    $streamPositions[$row->real_stream_name] = 0;
                }
                $this->streamPositions = array_merge($streamPositions, $this->streamPositions);

                return;
            }
            // stream names given
            foreach ($this->query['streams'] as $streamName) {
                $streamPositions[$streamName] = 0;
            }
            $this->streamPositions = array_merge($streamPositions, $this->streamPositions);
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
            $streamId = yield $this->determineStreamId($checkpointStream);
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
            '$s' => $this->streamPositions
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

            $streamId = yield $this->determineStreamId($streamName);

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
}
