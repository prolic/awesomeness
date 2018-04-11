<?php

declare(strict_types=1);

namespace Prooph\EventStore\Stats;

use Prooph\EventStore\Task\GetArrayTask;
use Prooph\EventStore\UserCredentials;

interface EventStoreStats
{
    public function getAllAsync(UserCredentials $userCredentials = null): GetArrayTask;

    public function getProcAsync(UserCredentials $userCredentials = null): GetArrayTask;

    public function getReplicationAsync(UserCredentials $userCredentials = null): GetArrayTask;

    public function getTcpAsync(UserCredentials $userCredentials = null): GetArrayTask;

    public function getSysAsync(UserCredentials $userCredentials = null): GetArrayTask;

    public function getEsAsync(UserCredentials $userCredentials = null): GetArrayTask;
}
