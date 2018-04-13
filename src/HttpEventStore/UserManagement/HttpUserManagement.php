<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\UserManagement;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\UserManagement\UserDetails;
use Prooph\EventStore\UserManagement\UserManagement;
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

final class HttpUserManagement implements UserManagement
{
    /** @var HttpClient */
    private $httpClient;
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
        $this->httpClient = $httpClient;
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

    public function changePassword(
        string $login,
        string $oldPassword,
        string $newPassword,
        UserCredentials $userCredentials = null
    ): void {
        (new ChangePasswordOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $oldPassword,
            $newPassword,
            $userCredentials ?? $this->settings->defaultUserCredentials()
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
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $fullName,
            $password,
            $groups,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function deleteUser(string $login, UserCredentials $userCredentials = null): void
    {
        (new DeleteUserOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function disableUser(string $login, UserCredentials $userCredentials = null): void
    {
        (new DisableUserOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function enableUser(string $login, UserCredentials $userCredentials = null): void
    {
        (new EnableUserOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function getUser(string $login, UserCredentials $userCredentials = null): UserDetails
    {
        return (new GetUserOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    /**
     * @return UserDetails[]
     */
    public function getAllUsers(UserCredentials $userCredentials = null): array
    {
        return (new GetAllUsersOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function resetPassword(string $login, string $newPassword, UserCredentials $userCredentials = null): void
    {
        (new ResetPasswordOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $newPassword,
            $userCredentials ?? $this->settings->defaultUserCredentials()
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
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $login,
            $fullName,
            $groups,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }
}
