<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Internal;

use Prooph\EventStore\RecordedEvent;

interface EventPublisher
{
    public function publish(RecordedEvent $event): void;
}
