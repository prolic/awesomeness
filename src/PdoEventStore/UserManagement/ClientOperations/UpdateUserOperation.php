<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\UserManagement\ClientOperations;

use Prooph\EventStore\UserCredentials;
use Prooph\PdoEventStore\PdoEventStoreConnection;

/** @internal */
class UpdateUserOperation
{
    public function __invoke(
        PdoEventStoreConnection $connection,
        string $login,
        string $fullName,
        array $groups,
        ?UserCredentials $userCredentials
    ): void {
        // @todo
    }
}
