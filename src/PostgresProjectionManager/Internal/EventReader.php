<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Internal;

use Amp\Coroutine;
use Amp\Loop;
use Amp\Postgres\Pool;
use Generator;
use SplQueue;

/** @internal */
abstract class EventReader
{
    /** @var Pool */
    protected $pool;
    /** @var SplQueue */
    protected $queue;
    /** @var bool */
    protected $stopOnEof;
    /** @var bool */
    protected $paused = true;
    protected $eof = false;

    public function __construct(Pool $pool, SplQueue $queue, bool $stopOnEof)
    {
        $this->pool = $pool;
        $this->queue = $queue;
        $this->stopOnEof = $stopOnEof;
    }

    public function run(): void
    {
        $this->paused = false;

        $run = function () use (&$run): Generator {
            yield new Coroutine($this->doRequestEvents());

            if (! $this->eof && ! $this->paused) {
                Loop::defer($run);
            }
        };

        Loop::defer($run);
    }

    public function pause(): void
    {
        $this->paused = true;
    }

    public function paused(): bool
    {
        return $this->paused;
    }

    public function eof(): bool
    {
        return $this->eof;
    }

    abstract protected function doRequestEvents(): Generator;
}
