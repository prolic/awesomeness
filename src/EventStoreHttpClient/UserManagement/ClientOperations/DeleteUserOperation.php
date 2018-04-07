<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\UserManagement\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStoreClient\Exception\AccessDenied;
use Prooph\EventStoreClient\Task;
use Prooph\EventStoreClient\UserCredentials;
use Prooph\EventStoreClient\UserManagement\UserNotFound;
use Prooph\EventStoreHttpClient\ClientOperations\Operation;
use Prooph\EventStoreHttpClient\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class DeleteUserOperation extends Operation
{
    /** @var string */
    private $login;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $login,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->login = $login;
    }

    public function task(): Task
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Delete,
            $this->uriFactory->createUri($this->baseUri . '/users/' . urlencode($this->login))
        );

        $promise = $this->sendAsyncRequest($request);

        return new Task($promise, function (ResponseInterface $response): void {
            switch ($response->getStatusCode()) {
                case 204:
                    return;
                case 401:
                    throw AccessDenied::toUserManagementOperation();
                case 404:
                    throw new UserNotFound();
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
