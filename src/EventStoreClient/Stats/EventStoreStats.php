<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Stats;

use Prooph\EventStoreClient\Task\GetArrayTask;
use Prooph\EventStoreClient\UserCredentials;

interface EventStoreStats
{
    public function getAll(UserCredentials $userCredentials = null): GetArrayTask;

    public function getProc(UserCredentials $userCredentials = null): GetArrayTask;

    public function getReplication(UserCredentials $userCredentials = null): GetArrayTask;

    public function getTcp(UserCredentials $userCredentials = null): GetArrayTask;

    public function getSys(UserCredentials $userCredentials = null): GetArrayTask;

    public function getEs(UserCredentials $userCredentials = null): GetArrayTask;
}
