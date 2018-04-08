<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Task;

use Prooph\EventStoreClient\ProjectionManagement\ProjectionDefinition;
use Prooph\EventStoreClient\Task as BaseTask;

/**
 * @internal
 * @method ProjectionDefinition result()
 */
class GetProjectionDefinitionTask extends BaseTask
{
}
