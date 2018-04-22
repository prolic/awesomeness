<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\UserManagement\ClientOperations;

use Prooph\EventStore\UserCredentials;
use Prooph\PdoEventStore\PdoEventStoreConnection;

/** @internal */
class ResetPasswordOperation
{
    public function __invoke(
        PdoEventStoreConnection $connection,
        string $login,
        string $newPassword,
        ?UserCredentials $userCredentials
    ): void {
        // @todo
    }
}
