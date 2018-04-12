<?php

declare(strict_types=1);
namespace Prooph\PdoEventStore;

use Prooph\EventStore\UserCredentials;

interface ConnectionSettings
{
    public function connectionString(): string;

    public function userCredentials(): UserCredentials;
}
