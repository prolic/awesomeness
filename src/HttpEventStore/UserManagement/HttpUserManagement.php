<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\UserManagement;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\UserManagement\AsyncUserManagement;
use Prooph\HttpEventStore\ConnectionSettings;
use Prooph\HttpEventStore\UserManagement\ClientOperations\ChangePasswordOperation;
use Prooph\HttpEventStore\UserManagement\ClientOperations\CreateUserOperation;
use Prooph\HttpEventStore\UserManagement\ClientOperations\DeleteUserOperation;
use Prooph\HttpEventStore\UserManagement\ClientOperations\DisableUserOperation;
use Prooph\HttpEventStore\UserManagement\ClientOperations\EnableUserOperation;
use Prooph\HttpEventStore\UserManagement\ClientOperations\GetAllUsersOperation;
use Prooph\HttpEventStore\UserManagement\ClientOperations\GetUserOperation;
use Prooph\HttpEventStore\UserManagement\ClientOperations\ResetPasswordOperation;
use Prooph\HttpEventStore\UserManagement\ClientOperations\UpdateUserOperation;
use Webmozart\Assert\Assert;

final class HttpUserManagement implements AsyncUserManagement
{
    /** @var HttpAsyncClient */
    private $asyncClient;
    /** @var RequestFactory */
    private $requestFactory;
    /** @var UriFactory */
    private $uriFactory;
    /** @var ConnectionSettings */
    private $settings;
    /** @var string */
    private $baseUri;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        ConnectionSettings $settings = null
    ) {
        $this->asyncClient = $asyncClient;
        $this->requestFactory = $requestFactory;
        $this->uriFactory = $uriFactory;
        $this->settings = $settings ?? ConnectionSettings::default();
        $this->baseUri = sprintf(
            '%s://%s:%s',
            $this->settings->useSslConnection() ? 'https' : 'http',
            $this->settings->endPoint()->host(),
            $this->settings->endPoint()->port()
        );
    }

    public function changePasswordAsync(
        string $login,
        string $oldPassword,
        string $newPassword,
        UserCredentials $userCredentials = null
    ): Task {
        $operation = new ChangePasswordOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $oldPassword,
            $newPassword,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

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
    ): Task {
        Assert::allString($groups, 'Expected an array of strings for groups');

        $operation = new CreateUserOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $fullName,
            $password,
            $groups,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function deleteUserAsync(string $login, UserCredentials $userCredentials = null): Task
    {
        $operation = new DeleteUserOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function disableUserAsync(string $login, UserCredentials $userCredentials = null): Task
    {
        $operation = new DisableUserOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function enableUserAsync(string $login, UserCredentials $userCredentials = null): Task
    {
        $operation = new EnableUserOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getUserAsync(string $login, UserCredentials $userCredentials = null): GetUserTask
    {
        $operation = new GetUserOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getAllUsersAsync(UserCredentials $userCredentials = null): GetAllUsersTask
    {
        $operation = new GetAllUsersOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function resetPasswordAsync(string $login, string $newPassword, UserCredentials $userCredentials = null): Task
    {
        $operation = new ResetPasswordOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $newPassword,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function updateUserAsync(
        string $login,
        string $fullName,
        array $groups,
        UserCredentials $userCredentials = null
    ): Task {
        Assert::allString($groups, 'Expected an array of strings for groups');

        $operation = new UpdateUserOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $fullName,
            $groups,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }
}
