<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ProjectionManagement\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\ProjectionManagement\ProjectionDefinition;
use Prooph\EventStore\Task\GetProjectionDefinitionTask;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;
use Prooph\HttpEventStore\ProjectionManagement\ProjectionNotFound;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class GetDefinitionOperation extends Operation
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

    public function task(): GetProjectionDefinitionTask
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri($this->baseUri . '/projection/' . urlencode($this->name) . '/query?config=true')
        );

        $promise = $this->sendAsyncRequest($request);

        return new GetProjectionDefinitionTask($promise, function (ResponseInterface $response): ProjectionDefinition {
            switch ($response->getStatusCode()) {
                case 200:
                    $json = json_decode($response->getBody()->getContents());

                    return new ProjectionDefinition(
                        $json['name'],
                        $json['query'],
                        $json['emitEnabled'],
                        $json['definition'],
                        $json['outputConfig']
                    );
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
