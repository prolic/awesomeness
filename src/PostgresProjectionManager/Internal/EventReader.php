<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Internal;

use Amp\Loop;
use Amp\Postgres\Pool;
use Amp\Sync\LocalMutex;
use Amp\Sync\Lock;
use Generator;
use SplQueue;

/** @internal */
abstract class EventReader
{
    protected const MaxReads = 400;

    /** @var LocalMutex */
    protected $readMutex;
    /** @var Pool */
    protected $pool;
    /** @var SplQueue */
    protected $queue;
    /** @var bool */
    protected $stopOnEof;
    /** @var bool */
    protected $paused = true;
    protected $eof = false;

    public function __construct(LocalMutex $readMutex, Pool $pool, SplQueue $queue, bool $stopOnEof)
    {
        $this->readMutex = $readMutex;
        $this->pool = $pool;
        $this->queue = $queue;
        $this->stopOnEof = $stopOnEof;
    }

    public function run(): void
    {
        $this->paused = false;

        Loop::repeat(0, function (string $watcherId): Generator {
            Loop::disable($watcherId);

            /** @var Lock $lock */
            $lock = yield $this->readMutex->acquire();

            yield from $this->doRequestEvents();

            $lock->release();

            if (! $this->eof && ! $this->paused) {
                Loop::enable($watcherId);
            }
        });
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
