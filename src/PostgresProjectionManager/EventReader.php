<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager;

use Amp\Loop;
use Amp\Postgres\Pool;
use Amp\Sync\LocalMutex;
use Amp\Sync\Lock;
use Generator;
use SplQueue;

/** @internal */
abstract class EventReader
{
    public const MaxReads = 400;
    //@todo add Principal ACL checks

    /** @var CheckpointTag */
    protected $checkpointTag;
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
    /** @var bool */
    protected $eof = false;

    public function __construct(
        CheckpointTag $checkpointTag,
        Pool $pool,
        SplQueue $queue,
        bool $stopOnEof
    ) {
        $this->checkpointTag = $checkpointTag;
        $this->readMutex = new LocalMutex();
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

    public function checkpointTag(): CheckpointTag
    {
        return $this->checkpointTag;
    }

    abstract protected function doRequestEvents(): Generator;

    abstract public function head(): Generator;
}
