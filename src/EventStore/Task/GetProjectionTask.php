<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\ProjectionManagement\ProjectionDetails;
use Prooph\EventStore\Task as BaseTask;

/**
 * @internal
 * @method ProjectionDetails result()
 */
class GetProjectionTask extends BaseTask
{
}
