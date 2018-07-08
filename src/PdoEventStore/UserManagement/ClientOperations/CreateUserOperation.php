<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\UserManagement\ClientOperations;

use PDO;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\Common\SystemStreams;
use Prooph\EventStore\Data\EventData;
use Prooph\EventStore\Data\EventId;
use Prooph\EventStore\Data\ExpectedVersion;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\UserManagement\UserManagement;
use Prooph\PdoEventStore\PdoEventStoreConnection;

/** @internal */
class CreateUserOperation
{
    public function __invoke(
        PdoEventStoreConnection $eventStoreConnection,
        PDO $connection,
        string $login,
        string $fullName,
        string $password,
        array $groups,
        ?UserCredentials $userCredentials
    ): void {
        $passwordHash = \password_hash($password, PASSWORD_DEFAULT);

        $connection->beginTransaction();

        try {
            $eventStoreConnection->appendToStream(
                SystemStreams::metastreamOf(UserManagement::UserStreamPrefix . $login),
                ExpectedVersion::Any,
                [
                    new EventData(
                        EventId::generate(),
                        SystemEventTypes::StreamMetadata,
                        true,
                        \json_encode([
                            '$acl' => [
                                '$w' => '$admins',
                                '$d' => '$admins',
                                '$mw' => '$admins',
                            ],
                            '$tb' => 1,
                        ]),
                        ''
                    ),
                ],
                $userCredentials
            );

            $eventStoreConnection->appendToStream(
                UserManagement::UserStreamPrefix . $login,
                ExpectedVersion::Any,
                [
                    new EventData(
                        EventId::generate(),
                        '$UserCreated',
                        true,
                        \json_encode([
                            'login' => $login,
                            'fullName' => $fullName,
                            'hash' => $passwordHash,
                            'groups' => $groups,
                        ]),
                        ''
                    ),
                ],
                $userCredentials
            );

            $eventStoreConnection->appendToStream(
                UserManagement::UsersStream,
                ExpectedVersion::Any,
                [
                    new EventData(
                        EventId::generate(),
                        UserManagement::UserEventType,
                        false,
                        $login,
                        ''
                    ),
                ]
            );

            $sql = 'INSERT INTO users (username, full_name, password_hash, disabled) VALUES (?, ?, ?, ?);';
            $statement = $connection->prepare($sql);
            $statement->execute([
                $login,
                $fullName,
                $passwordHash,
                false,
            ]);

            $sql = 'INSERT INTO user_roles (rolename, username) VALUES ' . \implode(', ', \array_fill(0, \count($groups), '(?, ?)'));
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
