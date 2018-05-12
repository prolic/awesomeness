<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager;

use Amp\Coroutine;
use Amp\Postgres\Pool;
use Amp\Promise;
use Generator;
use Prooph\EventStore\Exception\RuntimeException;

abstract class EventReader
{
    /** @var Pool */
    protected $pool;
    /** @var EventPublisher */
    protected $publisher;
    /** @var bool */
    protected $paused;
    /** @var bool */
    protected $pauseRequested;
    /** @var bool */
    protected $stopOnEof;

    public function __construct(Pool $pool, EventPublisher $publisher, bool $stopOnEof)
    {
        $this->pool = $pool;
        $this->publisher = $publisher;
        $this->paused = false;
        $this->pauseRequested = false;
        $this->stopOnEof = $stopOnEof;
    }

    public function resume(): void
    {
        if (! $this->pauseRequested) {
            throw new RuntimeException('Is not paused');
        }

        if (! $this->paused) {
            $this->pauseRequested = false;

            return;
        }

        $this->paused = false;
        $this->pauseRequested = false;

        //RequestEvents();
    }

    public function pause(): void
    {
        if ($this->pauseRequested) {
            throw new RuntimeException('Pause has been already requested');
        }

        $this->pauseRequested = true;
        //if (!AreEventsRequested())
        //    _paused = true;
    }

    public function requestEvents(): Promise
    {
        return new Coroutine($this->doRequestEvents());
    }

    abstract protected function doRequestEvents(): Generator;
}
