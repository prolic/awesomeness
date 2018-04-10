<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\Internal\ReplayParkedResult;
use Prooph\EventStore\Task as BaseTask;

/**
 * @internal
 * @method ReplayParkedResult result()
 */
class ReplayParkedTask extends BaseTask
{
}
