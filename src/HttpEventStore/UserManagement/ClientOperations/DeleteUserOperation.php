<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\UserManagement\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\UserNotFound;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class DeleteUserOperation extends Operation
{
    public function __invoke(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $login,
        ?UserCredentials $userCredentials
    ): void {
        $request = $requestFactory->createRequest(
            RequestMethod::Delete,
            $uriFactory->createUri($baseUri . '/users/' . urlencode($login))
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

        switch ($response->getStatusCode()) {
            case 204:
                return;
            case 401:
                throw AccessDenied::toUserManagementOperation();
            case 404:
                throw UserNotFound::withLogin($login);
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
