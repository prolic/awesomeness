<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\UserManagement\ClientOperations;

use PDO;
use Prooph\EventStore\Data\EventId;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\UserNotFound;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\UserManagement\UserManagement;
use Prooph\PdoEventStore\PdoEventStoreConnection;

/** @internal */
class ResetPasswordOperation
{
    public function __invoke(
        PdoEventStoreConnection $eventStoreConnection,
        PDO $connection,
        string $login,
        string $newPassword,
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

        $passwordHash = \password_hash($newPassword, PASSWORD_DEFAULT);
        $data = \json_decode($streamEventsSlice->events()[0]->data(), true);
        $data['hash'] = $passwordHash;

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
                        \json_encode($data),
                        ''
                    ),
                ],
                $userCredentials
            );

            $eventStoreConnection->appendToStream(
                UserManagement::UserPasswordNotificationsStream,
                ExpectedVersion::Any,
                [
                    new EventData(
                        EventId::generate(),
                        UserManagement::PasswordChanged,
                        true,
                        \json_encode(['loginName' => $login]),
                        ''
                    ),
                ],
                $userCredentials
            );

            $sql = 'UPDATE users SET password_hash = ? WHERE username = ?';
            $statement = $connection->prepare($sql);
            $statement->execute([$passwordHash, $login]);
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
