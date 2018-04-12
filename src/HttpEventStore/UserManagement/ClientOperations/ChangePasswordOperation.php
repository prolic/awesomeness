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
class ChangePasswordOperation extends Operation
{
    /** @var string */
    private $login;
    /** @var string */
    private $oldPassword;
    /** @var string */
    private $newPassword;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $login,
        string $oldPassword,
        string $newPassword,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->login = $login;
        $this->oldPassword = $oldPassword;
        $this->newPassword = $newPassword;
    }

    public function __invoke(): void
    {
        $string  = json_encode([
            'oldPassword' => $this->oldPassword,
            'newPassword' => $this->newPassword,
        ]);

        $request = $this->requestFactory->createRequest(
            RequestMethod::Post,
            $this->uriFactory->createUri($this->baseUri . '/users/' . urlencode($this->login) . '/command/change-password'),
            [
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($string),
            ],
            $string
        );

        $response = $this->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 200:
                return;
            case 401:
                throw AccessDenied::toUserManagementOperation();
            case 404:
                throw new UserNotFound();
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
