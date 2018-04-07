<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Task;

use Prooph\EventStoreClient\RecordedEvent;
use Prooph\EventStoreClient\Task as BaseTask;

/**
 * @internal
 * @method RecordedEvent[] result()
 */
class ReadFromSubscriptionTask extends BaseTask
{
}
