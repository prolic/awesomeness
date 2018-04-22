<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\UserManagement;

use MongoDB\Driver\Exception\AuthenticationException;
use PDO;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\UserManagement\UserDetails;
use Prooph\EventStore\UserManagement\UserManagement;
use Prooph\PdoEventStore\ClientOperations\LoadStreamIdOperation;
use Prooph\PdoEventStore\ClientOperations\LoadSystemSettingsOperation;
use Prooph\PdoEventStore\Internal\StreamOperation;
use Prooph\PdoEventStore\PdoEventStoreConnection;
use Prooph\PdoEventStore\UserManagement\ClientOperations\ChangePasswordOperation;
use Prooph\PdoEventStore\UserManagement\ClientOperations\CreateUserOperation;
use Prooph\PdoEventStore\UserManagement\ClientOperations\DeleteUserOperation;
use Prooph\PdoEventStore\UserManagement\ClientOperations\DisableUserOperation;
use Prooph\PdoEventStore\UserManagement\ClientOperations\EnableUserOperation;
use Prooph\PdoEventStore\UserManagement\ClientOperations\GetAllUsersOperation;
use Prooph\PdoEventStore\UserManagement\ClientOperations\GetUserOperation;
use Prooph\PdoEventStore\UserManagement\ClientOperations\ResetPasswordOperation;
use Prooph\PdoEventStore\UserManagement\ClientOperations\UpdateUserOperation;
use Webmozart\Assert\Assert;

final class PdoUserManagement implements UserManagement
{
    /** @var PdoEventStoreConnection */
    private $eventStoreConnection;
    /** @var PDO */
    private $connection;

    public function __construct(PdoEventStoreConnection $eventStoreConnection, PDO $connection)
    {
        $this->eventStoreConnection = $eventStoreConnection;
        $this->connection = $connection;
    }

    public function changePassword(
        string $login,
        string $oldPassword,
        string $newPassword,
        UserCredentials $userCredentials = null
    ): void {
        (new ChangePasswordOperation())(
            $this->eventStoreConnection,
            $this->connection,
            $login,
            $oldPassword,
            $newPassword,
            $userCredentials ?? $this->eventStoreConnection->settings()->defaultUserCredentials()
        );
    }

    /**
     * @param string $login
     * @param string $fullName
     * @param string $password
     * @param string[] $groups
     * @param UserCredentials|null $userCredentials
     * @return void
     */
    public function createUser(
        string $login,
        string $fullName,
        string $password,
        array $groups,
        UserCredentials $userCredentials = null
    ): void {
        Assert::allString($groups, 'Expected an array of strings for groups');

        (new CreateUserOperation())(
            $this->eventStoreConnection,
            $this->connection,
            $login,
            $fullName,
            $password,
            $groups,
            $userCredentials ?? $this->eventStoreConnection->settings()->defaultUserCredentials()
        );
    }

    public function deleteUser(string $login, UserCredentials $userCredentials = null): void
    {
        (new DeleteUserOperation())(
            $this->eventStoreConnection,
            $this->connection,
            $login,
            $userCredentials ?? $this->eventStoreConnection->settings()->defaultUserCredentials()
        );
    }

    public function disableUser(string $login, UserCredentials $userCredentials = null): void
    {
        (new DisableUserOperation())(
            $this->eventStoreConnection,
            $this->connection,
            $login,
            $userCredentials ?? $this->eventStoreConnection->settings()->defaultUserCredentials()
        );
    }

    public function enableUser(string $login, UserCredentials $userCredentials = null): void
    {
        (new EnableUserOperation())(
            $this->eventStoreConnection,
            $this->connection,
            $login,
            $userCredentials ?? $this->eventStoreConnection->settings()->defaultUserCredentials()
        );
    }

    public function getUser(string $login, UserCredentials $userCredentials = null): UserDetails
    {
        return (new GetUserOperation())(
            $this->eventStoreConnection,
            $this->connection,
            $login,
            $userCredentials ?? $this->eventStoreConnection->settings()->defaultUserCredentials()
        );
    }

    /**
     * @return UserDetails[]
     */
    public function getAllUsers(UserCredentials $userCredentials = null): array
    {
        return (new GetAllUsersOperation())(
            $this->eventStoreConnection,
            $this->connection,
            $userCredentials ?? $this->eventStoreConnection->settings()->defaultUserCredentials()
        );
    }

    public function resetPassword(string $login, string $newPassword, UserCredentials $userCredentials = null): void
    {
        (new ResetPasswordOperation())(
            $this->eventStoreConnection,
            $this->connection,
            $login,
            $newPassword,
            $userCredentials ?? $this->eventStoreConnection->settings()->defaultUserCredentials()
        );
    }

    public function updateUser(
        string $login,
        string $fullName,
        array $groups,
        UserCredentials $userCredentials = null
    ): void {
        Assert::allString($groups, 'Expected an array of strings for groups');

        (new UpdateUserOperation())(
            $this->eventStoreConnection,
            $this->connection,
            $login,
            $fullName,
            $groups,
            $userCredentials ?? $this->eventStoreConnection->settings()->defaultUserCredentials()
        );
    }
}
