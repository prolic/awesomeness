<?php

declare(strict_types=1);
namespace Prooph\PdoEventStore;

use Prooph\EventStore\Data\UserCredentials;

interface ConnectionSettings
{
    // dsn for pdo connection
    public function connectionString(): string;

    // credentials used for pdo connection
    public function pdoUserCredentials(): UserCredentials;

    // credentials used for event store
    public function defaultUserCredentials(): ?UserCredentials;
}
