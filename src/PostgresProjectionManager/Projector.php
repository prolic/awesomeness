<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager;

use Amp\Coroutine;
use Amp\Failure;
use Amp\Loop;
use Amp\Postgres\Pool as PostgresPool;
use Amp\Postgres\ResultSet;
use Amp\Postgres\Statement;
use Amp\Promise;
use Amp\Success;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use EmptyIterator;
use Iterator;
use PDO;
use PDOException;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\Exception;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Pdo\Exception\ProjectionNotCreatedException;
use Prooph\EventStore\Pdo\Exception\RuntimeException;
use Prooph\EventStore\Pdo\PdoEventStore;
use Prooph\EventStore\Projections\ProjectionState;
use Prooph\EventStore\RecordedEvent;
use Psr\Log\LoggerInterface as PsrLogger;
use Throwable;

class Projector
{
    /** @var EventStoreConnection */
    private $eventStoreConnection;
    /** @var PDO */
    private $connection;
    /** @var string */
    private $eventStreamsTable;
    /** @var string */
    private $projectionsTable;
    /** @var array */
    private $streamPositions = [];
    /** @var int */
    private $persistBlockSize;
    /** @var array */
    private $state = [];
    /** @var ProjectionStatus */
    private $status;
    /** @var callable|null */
    private $initCallback;
    /** @var Closure|null */
    private $handler;
    /** @var array */
    private $handlers = [];
    /** @var boolean */
    private $isStopped = false;
    /** @var ?string */
    private $currentStreamName = null;
    /** @var int lock timeout in milliseconds */
    private $lockTimeoutMs;
    /** @var int */
    private $eventCounter = 0;
    /** @var int */
    private $sleep;
    /** @var int */
    private $updateLockThreshold;
    /** @var array|null */
    private $query;
    /** @var string */
    private $vendor;
    /** @var DateTimeImmutable */
    private $lastLockUpdate;




    /** @var PostgresPool */
    private $postgresPool;
    /** @var string */
    private $name;
    /** @var string */
    private $id;
    /** @var PsrLogger */
    private $logger;
    /** @var ProjectionState */
    private $projectionState;

    public function __construct(
        PostgresPool $postgresPool,
        string $name,
        string $id,
        PsrLogger $logger
    ) {
        $this->postgresPool = $postgresPool;
        $this->name = $name;
        $this->id = $id;
        $this->logger = $logger;
        $this->projectionState = ProjectionState::stopped();

/*
        $this->eventStoreConnection = $eventStore;
        $this->connection = $connection;
        $this->name = $name;
        $this->eventStreamsTable = $eventStreamsTable;
        $this->projectionsTable = $projectionsTable;
        $this->lockTimeoutMs = $lockTimeoutMs;
        $this->persistBlockSize = $persistBlockSize;
        $this->sleep = $sleep;
        $this->status = ProjectionStatus::IDLE();
        $this->updateLockThreshold = $updateLockThreshold;
        $this->vendor = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        while ($eventStore instanceof EventStoreDecorator) {
            $eventStore = $eventStore->getInnerEventStore();
        }
        if (! $eventStore instanceof PdoEventStore) {
            throw new Exception\InvalidArgumentException('Unknown event store instance given');
        }
*/
    }

    public function start(): Promise
    {
        try {
            if ($this->projectionState->equals(ProjectionState::stopped())) {
                return new Coroutine($this->doStart());
            }

            return new Failure(new \Error(
                'Cannot start projection "' . $this->name . '": already ' . $this->projectionState->name()
            ));
        } catch (\Throwable $e) {
            return new Failure($e);
        }
    }

    private function doStart(): \Generator
    {
        $this->logger->debug('Checking status of projection "' . $this->name . '"');

        $this->state = ProjectionState::initial();

        try {
            /** @var Statement $statement */
            $statement = yield $this->postgresPool->prepare('SELECT projection_id, projection_name from projections');
            /** @var ResultSet $result */
            $result = yield $statement->execute();
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());
            yield $this->stop();
            yield new Failure($e);
        }

        $projectionIds = [];
        while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
            $projectionName = $result->getCurrent()->projection_name;
            $projectionId = $result->getCurrent()->projection_id;
            $projectionIds[] = $projectionId;
        }

        if ('$streams' === $this->name) {
            Loop::repeat(100, function (string $watcherId) {
                if ($this->projectionState->equals(ProjectionState::stopping())) {
                    Loop::cancel($watcherId);
                    $this->projectionState = ProjectionState::stopped();
                } else {
                    $this->logger->debug($this->name . ' Still running');
                }
            });;
        }

        $this->logger->debug('Started projection "' . $this->name .'"');

        $this->projectionState = ProjectionState::running();
    }
/*
    public function init(Closure $callback): Projector
    {
        if (null !== $this->initCallback) {
            throw new Exception\RuntimeException('Projection already initialized');
        }
        $callback = Closure::bind($callback, $this->createHandlerContext($this->currentStreamName));
        $result = $callback();
        if (is_array($result)) {
            $this->state = $result;
        }
        $this->initCallback = $callback;

        return $this;
    }

    public function fromStream(string $streamName): Projector
    {
        if (null !== $this->query) {
            throw new Exception\RuntimeException('From was already called');
        }
        $this->query['streams'][] = $streamName;

        return $this;
    }

    public function fromStreams(string ...$streamNames): Projector
    {
        if (null !== $this->query) {
            throw new Exception\RuntimeException('From was already called');
        }
        foreach ($streamNames as $streamName) {
            $this->query['streams'][] = $streamName;
        }

        return $this;
    }

    public function fromCategory(string $name): Projector
    {
        if (null !== $this->query) {
            throw new Exception\RuntimeException('From was already called');
        }
        $this->query['categories'][] = $name;

        return $this;
    }

    public function fromCategories(string ...$names): Projector
    {
        if (null !== $this->query) {
            throw new Exception\RuntimeException('From was already called');
        }
        foreach ($names as $name) {
            $this->query['categories'][] = $name;
        }

        return $this;
    }

    public function fromAll(): Projector
    {
        if (null !== $this->query) {
            throw new Exception\RuntimeException('From was already called');
        }
        $this->query['all'] = true;

        return $this;
    }

    public function when(array $handlers): Projector
    {
        if (null !== $this->handler || ! empty($this->handlers)) {
            throw new Exception\RuntimeException('When was already called');
        }
        foreach ($handlers as $eventName => $handler) {
            if (! is_string($eventName)) {
                throw new Exception\InvalidArgumentException('Invalid event name given, string expected');
            }
            if (! $handler instanceof Closure) {
                throw new Exception\InvalidArgumentException('Invalid handler given, Closure expected');
            }
            $this->handlers[$eventName] = Closure::bind($handler, $this->createHandlerContext($this->currentStreamName));
        }

        return $this;
    }

    public function whenAny(Closure $handler): Projector
    {
        if (null !== $this->handler || ! empty($this->handlers)) {
            throw new Exception\RuntimeException('When was already called');
        }
        $this->handler = Closure::bind($handler, $this->createHandlerContext($this->currentStreamName));

        return $this;
    }

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
        $this->projectionState = ProjectionState::stopping();

        return new Success();
        /*
        $this->persist();
        $this->isStopped = true;
        $projectionsTable = $this->quoteTableName($this->projectionsTable);
        $stopProjectionSql = <<<EOT
UPDATE $projectionsTable SET status = ? WHERE name = ?;
EOT;
        $statement = $this->connection->prepare($stopProjectionSql);
        try {
            $statement->execute([ProjectionStatus::IDLE()->getValue(), $this->name]);
        } catch (PDOException $exception) {
            // ignore and check error code
        }
        if ($statement->errorCode() !== '00000') {
            throw RuntimeException::fromStatementErrorInfo($statement->errorInfo());
        }
        $this->status = ProjectionStatus::IDLE();
        */
    }
/*
    public function getState(): array
    {
        return $this->state;
    }

    public function getName(): string
    {
        return $this->name;
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

    private function createLockUntilString(DateTimeImmutable $from): string
    {
        $micros = (string) ((int) $from->format('u') + ($this->lockTimeoutMs * 1000));
        $secs = substr($micros, 0, -6);
        if ('' === $secs) {
            $secs = 0;
        }
        $resultMicros = substr($micros, -6);

        return $from->modify('+' . $secs .' seconds')->format('Y-m-d\TH:i:s') . '.' . $resultMicros;
    }

    private function shouldUpdateLock(DateTimeImmutable $now): bool
    {
        if ($this->lastLockUpdate === null || $this->updateLockThreshold === 0) {
            return true;
        }
        $intervalSeconds = floor($this->updateLockThreshold / 1000);
        //Create an interval based on seconds
        $updateLockThreshold = new \DateInterval("PT{$intervalSeconds}S");
        //and manually add split seconds
        $updateLockThreshold->f = ($this->updateLockThreshold % 1000) / 1000;
        $threshold = $this->lastLockUpdate->add($updateLockThreshold);

        return $threshold <= $now;
    }

    private function quoteTableName(string $tableName): string
    {
        switch ($this->vendor) {
            case 'pgsql':
                return '"'.$tableName.'"';
            default:
                return "`$tableName`";
        }
    }

*/
}
