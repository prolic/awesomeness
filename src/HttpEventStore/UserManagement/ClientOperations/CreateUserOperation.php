<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\UserManagement\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class CreateUserOperation extends Operation
{
    /** @var string */
    private $login;
    /** @var string */
    private $fullName;
    /** @var string */
    private $password;
    /** @var string[] */
    private $groups;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $login,
        string $fullName,
        string $password,
        array $groups,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->login = $login;
        $this->fullName = $fullName;
        $this->password = $password;
        $this->groups = $groups;
    }

    public function __invoke(): void
    {
        $string = json_encode([
            'login' => $this->login,
            'fullName' => $this->fullName,
            'password' => $this->password,
            'groups' => $this->groups,
        ]);

        $request = $this->requestFactory->createRequest(
            RequestMethod::Post,
            $this->uriFactory->createUri($this->baseUri . '/users/'),
            [
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($string),
            ],
            $string
        );

        $response = $this->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 201:
                return;
            case 401:
                throw AccessDenied::toUserManagementOperation();
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
