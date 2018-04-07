<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Task;

use Prooph\EventStoreClient\Internal\ReplayParkedResult;
use Prooph\EventStoreClient\Task as BaseTask;

/**
 * @internal
 * @method ReplayParkedResult result()
 */
class ReplayParkedTask extends BaseTask
{
}
