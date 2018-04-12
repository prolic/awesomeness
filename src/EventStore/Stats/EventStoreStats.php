<?php

declare(strict_types=1);

namespace Prooph\EventStore\Stats;

use Prooph\EventStore\UserCredentials;

interface EventStoreStats
{
    public function getAll(UserCredentials $userCredentials = null): array;

    public function getProc(UserCredentials $userCredentials = null): array;

    public function getReplication(UserCredentials $userCredentials = null): array;

    public function getTcp(UserCredentials $userCredentials = null): array;

    public function getSys(UserCredentials $userCredentials = null): array;

    public function getEs(UserCredentials $userCredentials = null): array;
}
