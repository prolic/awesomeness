<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\Task as BaseTask;

/**
 * @internal
 * @method RecordedEvent[] result()
 */
class ReadFromSubscriptionTask extends BaseTask
{
}
