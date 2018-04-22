<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\UserManagement\ClientOperations;

use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\UserManagement\UserDetails;
use Prooph\EventStore\UserManagement\UserNotFound;
use Prooph\PdoEventStore\PdoEventStoreConnection;

/** @internal */
class GetUserOperation
{
    public function __invoke(
        PdoEventStoreConnection $connection,
        string $login,
        ?UserCredentials $userCredentials
    ): UserDetails {
        try {
            $streamEventsSlice = $connection->readStreamEventsBackward(
                '$user-' . $login,
                PHP_INT_MAX,
                1,
                true,
                $userCredentials
            );
        } catch (AccessDenied $e) {
            throw AccessDenied::toUserManagementOperation();
        }

        if (! $streamEventsSlice->status()->equals(SliceReadStatus::success())) {
            throw UserNotFound::withLogin($login);
        }

        $data = json_decode($streamEventsSlice->events()[0]->data(), true);

        return new UserDetails(
            $data['login'],
            $data['fullName'],
            $data['groups'],
            $data['disabled'] ?? false
        );
    }
}
