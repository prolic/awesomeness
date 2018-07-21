<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\UserManagement\ClientOperations;

use PDO;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Data\UserData;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\UserManagement\UserManagement;
use Prooph\PdoEventStore\PdoEventStoreSyncSyncConnection;

// @todo refactor to use new UserData object

/** @internal */
class GetAllUsersOperation
{
    /**
     * @return UserData[]
     */
    public function __invoke(
        PdoEventStoreSyncSyncConnection $eventStoreConnection,
        PDO $connection,
        ?UserCredentials $userCredentials
    ): array {
        try {
            $eventStoreConnection->readStreamEventsBackward(
                UserManagement::UsersStream,
                PHP_INT_MAX,
                1,
                true,
                $userCredentials
            );
        } catch (AccessDenied $e) {
            throw AccessDenied::toUserManagementOperation();
        }

        switch ($connection->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'mysql':
                $concat = "GROUP_CONCAT(ur.rolename SEPARATOR ',') as rolenames";
                break;
            case 'pgsql':
                $concat = "STRING_AGG(ur.rolename, ',') as rolenames";
                break;
            default:
                throw new RuntimeException('Invalid PDO driver used');
        }

        $sql = "SELECT u.*, $concat from users u LEFT JOIN user_roles ur ON u.username = ur.username GROUP BY u.username;";
        $statement = $connection->prepare($sql);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_OBJ);

        $userData = [];

        while ($row = $statement->fetch()) {
            $userData[] = new UserData( // @todo
                $row->username,
                $row->full_name,
                \explode(',', $row->rolenames),
                $row->disabled
            );
        }

        return $userData;
    }
}
