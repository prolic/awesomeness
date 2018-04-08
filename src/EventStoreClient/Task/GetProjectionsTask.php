<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Task;

use Prooph\EventStoreClient\ProjectionManagement\ProjectionDetails;
use Prooph\EventStoreClient\Task as BaseTask;

/**
 * @internal
 * @method ProjectionDetails[] result()
 */
class GetProjectionsTask extends BaseTask
{
}
