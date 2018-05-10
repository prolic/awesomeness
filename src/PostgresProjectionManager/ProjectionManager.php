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
use Error;
use Generator;
use Prooph\EventStore\Exception\ProjectionNotFound;
use Psr\Log\LoggerInterface as PsrLogger;
use Throwable;
use function assert;

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

    /** @var int */
    private $state = self::STOPPED;
    /** @var PostgresPool */
    private $postgresPool;
    /** @var PsrLogger */
    private $logger;
    /** @var Projector[] */
    private $projectors = [];

    public function __construct(PostgresPool $pool, PsrLogger $logger)
    {
        $this->postgresPool = $pool;
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
        assert($this->logger->debug('Starting') || true);

        $this->state = self::STARTING;

        try {
            /** @var Statement $statement */
            $statement = yield $this->postgresPool->prepare('SELECT projection_id, projection_name from projections');
            /** @var ResultSet $result */
            $result = yield $statement->execute();
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());
            Loop::stop();
            yield new Failure($e);
        }

        while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
            $projectionName = $result->getCurrent()->projection_name;
            $projectionId = $result->getCurrent()->projection_id;

            $this->logger->debug('Found projection ' . $projectionName);

            $projector = new Projector($this->postgresPool, $projectionName, $projectionId, $this->logger);
            $this->projectors[$projectionName] = $projector;

            yield $projector->start();
        }

        $this->state = self::STARTED;
        assert($this->logger->debug('Started') || true);
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
                return new Coroutine($this->doStop($timeout));
            case self::STOPPED:
                return new Success();
            default:
                return new Failure(new \Error(
                    'Cannot stop projection manager: currently '.self::STATES[$this->state]
                ));
        }
    }

    public function stopProjection(string $name): Promise
    {
        if (! isset($this->projectors[$name])) {
            return new Failure(ProjectionNotFound::withName($name));
        }

        return $this->projectors[$name]->stop();
    }

    private function doStop(int $timeout): Generator
    {
        assert($this->logger->debug('Stopping') || true);
        $this->state = self::STOPPING;

        // @todo stop all projections
        yield new Success();

        assert($this->logger->debug('Stopped') || true);
        $this->state = self::STOPPED;
    }
}
