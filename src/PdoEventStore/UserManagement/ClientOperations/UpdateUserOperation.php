<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\UserManagement\ClientOperations;

use PDO;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\UserManagement\UserManagement;
use Prooph\EventStore\UserManagement\UserNotFound;
use Prooph\PdoEventStore\PdoEventStoreConnection;

/** @internal */
class UpdateUserOperation
{
    public function __invoke(
        PdoEventStoreConnection $eventStoreConnection,
        PDO $connection,
        string $login,
        string $fullName,
        array $groups,
        ?UserCredentials $userCredentials
    ): void {
        try {
            $streamEventsSlice = $eventStoreConnection->readStreamEventsBackward(
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

        $data['fullName'] = $fullName;
        $data['groups'] = $groups;

        $connection->beginTransaction();

        try {
            $eventStoreConnection->appendToStream(
                '$user-' . $login,
                ExpectedVersion::Any,
                [
                    new EventData(
                        EventId::generate(),
                        UserManagement::UserUpdated,
                        true,
                        json_encode($data),
                        ''
                    ),
                ],
                $userCredentials
            );

            $sql = 'UPDATE users set full_name = ? WHERE username = ?;';
            $statement = $connection->prepare($sql);
            $statement->execute([$fullName, $login]);

            $sql = 'DELETE FROM user_roles where username = ?;';
            $statement = $connection->prepare($sql);
            $statement->execute([$login]);

            $sql = 'INSERT INTO user_roles (rolename, username) VALUES ' . implode(', ', array_fill(0, count($groups), '(?, ?)'));
            $statement = $connection->prepare($sql);
            $params = [];
            foreach ($groups as $group) {
                $params[] = $group;
                $params[] = $login;
            }
            $statement->execute($params);
        } catch (AccessDenied $e) {
            $connection->rollBack();

            throw AccessDenied::toUserManagementOperation();
        } catch (\Exception $e) {
            $connection->rollBack();

            throw $e;
        }

        $connection->commit();
    }
}
