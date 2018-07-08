<?php

declare(strict_types=1);

namespace Prooph\EventStore\Stats;

use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Task\GetArrayTask;

interface AsyncEventStoreStats
{
    public function getAllAsync(UserCredentials $userCredentials = null): GetArrayTask;

    public function getProcAsync(UserCredentials $userCredentials = null): GetArrayTask;

    public function getReplicationAsync(UserCredentials $userCredentials = null): GetArrayTask;

    public function getTcpAsync(UserCredentials $userCredentials = null): GetArrayTask;

    public function getSysAsync(UserCredentials $userCredentials = null): GetArrayTask;

    public function getEsAsync(UserCredentials $userCredentials = null): GetArrayTask;
}
