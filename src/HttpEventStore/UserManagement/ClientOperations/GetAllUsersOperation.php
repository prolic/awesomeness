<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\UserManagement\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Data\UserData;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;

// @todo refactor to use new UserData object

/** @internal */
class GetAllUsersOperation extends Operation
{
    /**
     * @return UserData[]
     */
    public function __invoke(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        ?UserCredentials $userCredentials
    ): array {
        $request = $requestFactory->createRequest(
            RequestMethod::Get,
            $uriFactory->createUri($baseUri . '/users/')
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

        switch ($response->getStatusCode()) {
            case 200:
                $json = \json_decode($response->getBody()->getContents(), true);

                $userData = [];
                foreach ($json['data'] as $user) {
                    $userData[] = new UserData( // @todo
                        $user['login'],
                        $user['fullName'],
                        $user['groups'],
                        $user['disabled']
                    );
                }

                return $userData;
            case 401:
                throw AccessDenied::toUserManagementOperation();
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
