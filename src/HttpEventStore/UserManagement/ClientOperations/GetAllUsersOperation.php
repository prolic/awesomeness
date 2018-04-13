<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\UserManagement\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\UserManagement\UserDetails;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class GetAllUsersOperation extends Operation
{
    /**
     * @return UserDetails[]
     */
    public function __invoke(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $login,
        ?UserCredentials $userCredentials
    ): array {
        $request = $requestFactory->createRequest(
            RequestMethod::Post,
            $uriFactory->createUri($baseUri . '/users/' . urlencode($stream))
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

        switch ($response->getStatusCode()) {
            case 200:
                $json = json_decode($response->getBody()->getContents(), true);

                $userDetails = [];
                foreach ($json['data'] as $user) {
                    $userDetails[] = new UserDetails(
                        $user['login'],
                        $user['fullName'],
                        $user['groups'],
                        $user['disabled'],
                        $json['links']
                    );
                }

                return $userDetails;
            case 401:
                throw AccessDenied::toUserManagementOperation();
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
