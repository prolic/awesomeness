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
    /** @var int */
    protected $pendingEventsThreshold;

    public function __construct(Pool $pool, SplQueue $queue, bool $stopOnEof, int $pendingEventsThreshold)
    {
        $this->pool = $pool;
        $this->queue = $queue;
        $this->stopOnEof = $stopOnEof;
        $this->pendingEventsThreshold = $pendingEventsThreshold;
    }

    public function run(): string
    {
        return Loop::repeat(0, function (string $watcherId): Generator {
            yield new Coroutine($this->doRequestEvents($watcherId));

            if ($this->queue->count() > $this->pendingEventsThreshold) {
                Loop::disable($watcherId);
            }
        });
    }

    abstract protected function doRequestEvents(string $watcherId): Generator;
}
