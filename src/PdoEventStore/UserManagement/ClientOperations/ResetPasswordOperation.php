<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\UserManagement\ClientOperations;

use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\UserManagement\UserNotFound;
use Prooph\PdoEventStore\Http\RequestMethod;
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
