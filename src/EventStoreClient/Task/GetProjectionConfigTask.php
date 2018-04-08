<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Task;

use Prooph\EventStoreClient\ProjectionManagement\ProjectionConfig;
use Prooph\EventStoreClient\Task as BaseTask;

/**
 * @internal
 * @method ProjectionConfig result()
 */
class GetProjectionConfigTask extends BaseTask
{
}
