<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\ProjectionManagement\ProjectionDefinition;
use Prooph\EventStore\Task as BaseTask;

/**
 * @internal
 * @method ProjectionDefinition result()
 */
class GetProjectionDefinitionTask extends BaseTask
{
}
