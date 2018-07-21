<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\UserManagement\ClientOperations;

use PDO;
use Prooph\EventStore\Data\EventData;
use Prooph\EventStore\Data\EventId;
use Prooph\EventStore\Data\ExpectedVersion;
use Prooph\EventStore\Data\SliceReadStatus;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\UserNotFound;
use Prooph\EventStore\UserManagement\UserManagement;
use Prooph\PdoEventStore\PdoEventStoreSyncSyncConnection;

/** @internal */
class EnableUserOperation
{
    public function __invoke(
        PdoEventStoreSyncSyncConnection $eventStoreConnection,
        PDO $connection,
        string $login,
        ?UserCredentials $userCredentials
    ): void {
        $connection->beginTransaction();

        try {
            $streamEventsSlice = $eventStoreConnection->readStreamEventsBackward(
                '$user-' . $login,
                PHP_INT_MAX,
                1,
                true,
                $userCredentials
            );

            if (! $streamEventsSlice->status()->equals(SliceReadStatus::success())) {
                throw UserNotFound::withLogin($login);
            }

            $data = \json_decode($streamEventsSlice->events()[0]->data(), true);

            unset($data['disabled']);

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
                ]
            );

            $sql = 'UPDATE users SET disabled = ? WHERE username = ?;';
            $statement = $connection->prepare($sql);
            $statement->execute([
                false,
                $login,
            ]);
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
