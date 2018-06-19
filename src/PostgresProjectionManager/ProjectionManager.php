<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager;

use Amp\Coroutine;
use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\Parallel\Context\Process;
use Amp\Postgres\Connection;
use Amp\Postgres\Pool;
use Amp\Postgres\ResultSet;
use Amp\Postgres\Statement;
use Amp\Process\StatusError;
use Amp\Promise;
use Amp\Success;
use Error;
use Generator;
use Prooph\EventStore\Exception\ProjectionNotFound;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\SystemSettings;
use Prooph\PostgresProjectionManager\Operations\LoadSystemSettingsOperation;
use Psr\Log\LoggerInterface as PsrLogger;
use Throwable;
use function Amp\call;
use function Amp\Log\hasColorSupport;

/** @internal */
class ProjectionManager
{
    private const STOPPED = 0;
    private const STARTING = 1;
    private const STARTED = 2;
    private const STOPPING = 3;

    private const STATES = [
        self::STOPPED => 'STOPPED',
        self::STARTING => 'STARTING',
        self::STARTED => 'STARTED',
        self::STOPPING => 'STOPPING',
    ];

    private const DEFAULT_SHUTDOWN_TIMEOUT = 3000;

    /** @var string */
    private $connectionString;
    /** @var int */
    private $state = self::STOPPED;
    /** @var Pool */
    private $pool;
    /** @var PsrLogger */
    private $logger;
    /** @var Process[] */
    private $projections = [];
    /** @var SystemSettings */
    private $settings;
    /** @var Connection */
    private $lockConnection;

    public function __construct(string $connectionString, PsrLogger $logger)
    {
        $this->connectionString = $connectionString;
        $this->pool = new Pool($connectionString);
        $this->logger = $logger;
    }

    public function start(): Promise
    {
        try {
            if ($this->state === self::STOPPED) {
                return new Coroutine($this->doStart());
            }

            return new Failure(new Error(
                'Cannot start server: already '.self::STATES[$this->state]
            ));
        } catch (Throwable $uncaught) {
            return new Failure($uncaught);
        }
    }

    private function doStart(): Generator
    {
        \assert($this->logger->debug('Starting') || true);

        $this->state = self::STARTING;

        try {
            $this->lockConnection = yield $this->pool->extractConnection();
            /** @var Statement $statement */
            $statement = yield $this->lockConnection->prepare('SELECT PG_TRY_ADVISORY_LOCK(HASHTEXT(:name)) as stream_lock;');
            /** @var ResultSet $result */
            $result = yield $statement->execute(['name' => 'projection-manager']);
            yield $result->advance(ResultSet::FETCH_OBJECT);
            $lock = $result->getCurrent()->stream_lock;

            if (! $lock) {
                throw new RuntimeException(
                    'Could not acquire lock for projection manager, another process already running?'
                );
            }

            $this->settings = yield new Coroutine((new LoadSystemSettingsOperation())($this->pool));

            /** @var ResultSet $result */
            $result = yield $this->pool->execute('SELECT projection_id, projection_name from projections;');

            while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
                $projectionName = $result->getCurrent()->projection_name;
                $projectionId = $result->getCurrent()->projection_id;

                $context = Process::run(__DIR__ . '/projection-process.php', null, [
                    'prooph_connection_string' => $this->connectionString,
                    'prooph_projection_id' => $projectionId,
                    'prooph_projection_name' => $projectionName,
                    'prooph_log_level' => 'DEBUG', //@todo make configurable
                    'AMP_LOG_COLOR' => hasColorSupport(),
                ]);

                $pid = yield $context->getPid();

                $this->logger->debug($projectionName . ' :: ' . $pid);

                $this->projections[$projectionName] = $context;

                yield new Delayed(100); // waiting for the projection to start
            }

            $this->state = self::STARTED;
            \assert($this->logger->debug('Started') || true);
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());
            Loop::stop();
            yield new Failure($e);
        }
    }

    /**
     * Stop the server.
     *
     * @param int $timeout Number of milliseconds to allow clients to gracefully shutdown before forcefully closing.
     *
     * @return Promise
     */
    public function stop(int $timeout = self::DEFAULT_SHUTDOWN_TIMEOUT): Promise
    {
        switch ($this->state) {
            case self::STARTED:
                return call(function () use ($timeout): Generator {
                    \assert($this->logger->debug('Stopping') || true);
                    $this->state = self::STOPPING;

                    foreach ($this->projections as $name => $context) {
                        yield $context->join();
                        unset($this->projections[$name]);
                    }

                    \assert($this->logger->debug('Stopped') || true);
                    $this->state = self::STOPPED;
                });
            case self::STOPPED:
                return new Success();
            default:
                return new Failure(new \Error(
                    'Cannot stop projection manager: currently '.self::STATES[$this->state]
                ));
        }
    }

    public function disableProjection(string $name): Promise
    {
        return $this->sendToProjection($name, 'disable');
    }

    public function enableProjection(string $name): Promise
    {
        return $this->sendToProjection($name, 'enable');
    }

    public function resetProjection(string $name, $enableRunAs): Promise
    {
        return $this->sendToProjection($name, 'reset', [$enableRunAs]);
    }

    public function getState(string $name): Promise
    {
        return $this->sendToAndReceiveFromProjection($name, 'state');
    }

    public function getConfig(string $name): Promise
    {
        return $this->sendToAndReceiveFromProjection($name, 'config');
    }

    public function getDefinition(string $name): Promise
    {
        return $this->sendToAndReceiveFromProjection($name, 'query');
    }

    public function getStatistics(string $name): Promise
    {
        return $this->sendToAndReceiveFromProjection($name, 'statistics');
    }

    private function sendToProjection(string $name, string $operation, array $args = []): Promise
    {
        if (! isset($this->projections[$name])) {
            return new Failure(ProjectionNotFound::withName($name));
        }

        $value = $operation . '::' . \serialize($args);

        return $this->projections[$name]->send($value);
    }

    private function sendToAndReceiveFromProjection(string $name, string $operation, array $args = []): Promise
    {
        if (! isset($this->projections[$name])) {
            return new Failure(ProjectionNotFound::withName($name));
        }

        $value = $operation . '::' . \serialize($args);

        return call(function () use ($name, $value): Generator {
            yield $this->projections[$name]->send($value);

            return yield $this->projections[$name]->receive();
        });
    }
}
