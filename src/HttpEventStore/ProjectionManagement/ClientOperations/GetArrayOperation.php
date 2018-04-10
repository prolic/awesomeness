<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ProjectionManagement\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Task\GetArrayTask;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;
use Prooph\HttpEventStore\ProjectionManagement\ProjectionNotFound;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class GetArrayOperation extends Operation
{
    /** @var string */
    private $name;
    /** @var string */
    private $urlQuery;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $name,
        string $urlQuery,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->name = $name;
        $this->urlQuery = $urlQuery;
    }

    public function task(): GetArrayTask
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri($this->baseUri . '/projection/' . urlencode($this->name) . '/' . $this->urlQuery)
        );

        $promise = $this->sendAsyncRequest($request);

        return new GetArrayTask($promise, function (ResponseInterface $response): array {
            switch ($response->getStatusCode()) {
                case 200:
                    return json_decode($response->getBody()->getContents()) ?? [];
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
