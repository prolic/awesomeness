<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\UserManagement\ClientOperations;

use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Task\GetAllUsersTask;
use Prooph\EventStore\UserManagement\UserDetails;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class GetAllUsersOperation extends Operation
{
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
