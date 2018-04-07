<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\UserManagement\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStoreClient\Exception\AccessDenied;
use Prooph\EventStoreClient\Task\GetAllUsersTask;
use Prooph\EventStoreClient\UserCredentials;
use Prooph\EventStoreClient\UserManagement\UserDetails;
use Prooph\EventStoreHttpClient\ClientOperations\Operation;
use Prooph\EventStoreHttpClient\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class GetAllUsersOperation extends Operation
{
    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);
    }

    public function task(): GetAllUsersTask
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Post,
            $this->uriFactory->createUri($this->baseUri . '/users/' . urlencode($this->stream))
        );

        $promise = $this->sendAsyncRequest($request);

        return new GetAllUsersTask($promise, function (ResponseInterface $response): array {
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
        });
    }
}
