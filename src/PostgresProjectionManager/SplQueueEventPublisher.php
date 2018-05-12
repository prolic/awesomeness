<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager;

use Prooph\EventStore\RecordedEvent;
use SplQueue;

final class SplQueueEventPublisher implements EventPublisher
{
    /** @var SplQueue */
    private $queue;

    public function __construct(SplQueue $queue)
    {
        $this->queue = $queue;
    }

    public function publish(RecordedEvent $event): void
    {
        $this->queue->enqueue($event);
    }
}
