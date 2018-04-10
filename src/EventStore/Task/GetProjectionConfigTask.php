<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\ProjectionManagement\ProjectionConfig;
use Prooph\EventStore\Task as BaseTask;

/**
 * @internal
 * @method ProjectionConfig result()
 */
class GetProjectionConfigTask extends BaseTask
{
}
