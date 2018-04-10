<?php

declare(strict_types=1);

namespace Prooph\EventStore\Task;

use Prooph\EventStore\Task as BaseTask;
use Prooph\EventStore\UserManagement\UserDetails;

/**
 * @internal
 * @method UserDetails result()
 */
class GetUserTask extends BaseTask
{
}
