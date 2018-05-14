<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Internal;

use Amp\Coroutine;
use Amp\Postgres\Pool;
use Amp\Promise;
use Generator;
use Prooph\EventStore\Exception\RuntimeException;
use SplQueue;

/** @internal */
abstract class EventReader
{
    /** @var Pool */
    protected $pool;
    /** @var SplQueue */
    protected $queue;
    /** @var bool */
    protected $paused = true;
    /** @var bool */
    protected $stopOnEof;

    public function __construct(Pool $pool, SplQueue $queue, bool $stopOnEof)
    {
        $this->pool = $pool;
        $this->queue = $queue;
        $this->stopOnEof = $stopOnEof;
    }

    public function resume(): Promise
    {
        if (! $this->paused) {
            throw new RuntimeException('Is not paused');
        }

        $this->paused = false;

        return $this->requestEvents();
    }

    public function pause(): void
    {
        $this->paused = true;
    }

    private function requestEvents(): Promise
    {
        return new Coroutine($this->doRequestEvents());
    }

    abstract protected function doRequestEvents(): Generator;
}
