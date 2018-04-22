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
use Prooph\EventStore\UserManagement\UserNotFound;
use Prooph\PdoEventStore\PdoEventStoreConnection;

/** @internal */
class DisableUserOperation
{
    public function __invoke(
        PdoEventStoreConnection $eventStoreConnection,
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
                throw new UserNotFound();
            }

            $data = json_decode($streamEventsSlice->events()[0]->data(), true);

            $data['disabled'] = true;

            $eventStoreConnection->appendToStream(
                '$user-' . $login,
                ExpectedVersion::Any,
                [
                    new EventData(
                        EventId::generate(),
                        '$UserUpdated',
                        true,
                        json_encode($data),
                        ''
                    ),
                ]
            );

            $sql = 'UPDATE users SET disabled = ? WHERE username = ?;';
            $statement = $connection->prepare($sql);
            $statement->execute([
                true,
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
