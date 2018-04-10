<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\AllEventsSlice;
use Prooph\EventStore\Task as BaseTask;

/**
 * @internal
 * @method AllEventsSlice result()
 */
class AllEventsSliceTask extends BaseTask
{
}
