<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\UserManagement\ClientOperations;

use PDO;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\UserManagement\UserManagement;
use Prooph\PdoEventStore\PdoEventStoreSyncSyncConnection;

/** @internal */
class DeleteUserOperation
{
    public function __invoke(
        PdoEventStoreSyncSyncConnection $eventStoreConnection,
        PDO $connection,
        string $login,
        ?UserCredentials $userCredentials
    ): void {
        $connection->beginTransaction();

        try {
            $eventStoreConnection->deleteStream(
                UserManagement::UserStreamPrefix . $login,
                true,
                $userCredentials
            );

            $sql = 'DELETE FROM user_roles WHERE username = ?;';
            $statement = $connection->prepare($sql);
            $statement->execute([$login]);

            $sql = 'DELETE FROM users WHERE username = ?;';
            $statement = $connection->prepare($sql);
            $statement->execute([$login]);
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
