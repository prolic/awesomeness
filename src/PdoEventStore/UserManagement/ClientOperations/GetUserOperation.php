<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\UserManagement\ClientOperations;

use Prooph\EventStore\Data\SliceReadStatus;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Data\UserData;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\UserNotFound;
use Prooph\EventStore\UserManagement\UserManagement;
use Prooph\PdoEventStore\PdoEventStoreConnection;

// @todo refactor to use new UserData object

/** @internal */
class GetUserOperation
{
    public function __invoke(
        PdoEventStoreConnection $connection,
        string $login,
        ?UserCredentials $userCredentials
    ): UserData {
        try {
            $streamEventsSlice = $connection->readStreamEventsBackward(
                UserManagement::UserStreamPrefix . $login,
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

        $data = \json_decode($streamEventsSlice->events()[0]->data(), true);

        return new UserData( // @todo
            $data['login'],
            $data['fullName'],
            $data['groups'],
            $data['disabled'] ?? false
        );
    }
}
