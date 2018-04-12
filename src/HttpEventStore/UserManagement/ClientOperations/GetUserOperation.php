<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\UserManagement\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\UserManagement\UserDetails;
use Prooph\EventStore\UserManagement\UserNotFound;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class GetUserOperation extends Operation
{
    /** @var string */
    private $login;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $login,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->login = $login;
    }

    public function __invoke(): UserDetails
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri($this->baseUri . '/users/' . urlencode($this->login))
        );

        $response = $this->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 200:
                $json = json_decode($response->getBody()->getContents(), true);

                return new UserDetails(
                    $json['data']['login'],
                    $json['data']['fullName'],
                    $json['data']['groups'],
                    $json['data']['disabled'],
                    $json['data']['links']
                );
            case 401:
                throw AccessDenied::toUserManagementOperation();
            case 404:
                throw new UserNotFound();
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
