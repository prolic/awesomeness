<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ProjectionManagement\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStoreClient\Exception\AccessDenied;
use Prooph\EventStoreClient\Task;
use Prooph\EventStoreClient\UserCredentials;
use Prooph\EventStoreHttpClient\ClientOperations\Operation;
use Prooph\EventStoreHttpClient\Http\RequestMethod;
use Prooph\EventStoreHttpClient\ProjectionManagement\ProjectionNotFound;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class ResetOperation extends Operation
{
    /** @var string */
    private $name;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $name,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->name = $name;
    }

    public function task(): Task
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Post,
            $this->uriFactory->createUri($this->baseUri . '/projection/' . urlencode($this->name) . '/command/reset')
        );

        $promise = $this->sendAsyncRequest($request);

        return new Task($promise, function (ResponseInterface $response): void {
            switch ($response->getStatusCode()) {
                case 200:
                    return;
                case 401:
                    throw AccessDenied::toUserManagementOperation();
                case 404:
                    throw new ProjectionNotFound();
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
