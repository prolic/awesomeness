<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Task;

use Prooph\EventStoreClient\Task as BaseTask;
use Prooph\EventStoreClient\UserManagement\UserDetails;

/**
 * @internal
 * @method UserDetails[] result()
 */
class GetAllUsersTask extends BaseTask
{
}
