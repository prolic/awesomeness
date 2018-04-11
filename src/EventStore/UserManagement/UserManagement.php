<?php

declare(strict_types=1);

namespace Prooph\EventStore\UserManagement;

use Prooph\EventStore\Task;
use Prooph\EventStore\Task\GetAllUsersTask;
use Prooph\EventStore\Task\GetUserTask;
use Prooph\EventStore\UserCredentials;

/** @internal */
interface UserManagement
{
    public function changePasswordAsync(
        string $login,
        string $oldPassword,
        string $newPassword,
        UserCredentials $userCredentials = null
    ): Task;

    /**
     * @param string $login
     * @param string $fullName
     * @param string $password
     * @param string[] $groups
     * @param UserCredentials|null $userCredentials
     * @return Task
     */
    public function createUserAsync(
        string $login,
        string $fullName,
        string $password,
        array $groups,
        UserCredentials $userCredentials = null
    ): Task;

    public function deleteUserAsync(string $login, UserCredentials $userCredentials = null): Task;

    public function disableUserAsync(string $login, UserCredentials $userCredentials = null): Task;

    public function enableUserAsync(string $login, UserCredentials $userCredentials = null): Task;

    public function getUserAsync(string $login, UserCredentials $userCredentials = null): GetUserTask;

    public function getAllUsersAsync(UserCredentials $userCredentials = null): GetAllUsersTask;

    public function resetPasswordAsync(string $login, string $newPassword, UserCredentials $userCredentials = null): Task;

    /**
     * @param string $login
     * @param string $fullName
     * @param string[] $groups
     * @param UserCredentials|null $userCredentials
     * @return Task
     */
    public function updateUserAsync(
        string $login,
        string $fullName,
        array $groups,
        UserCredentials $userCredentials = null
    ): Task;
}
