<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ProjectionManagement\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\ProjectionManagement\ProjectionConfig;
use Prooph\EventStore\Task\GetProjectionConfigTask;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;
use Prooph\HttpEventStore\ProjectionManagement\ProjectionNotFound;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class GetConfigOperation extends Operation
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

    public function task(): GetProjectionConfigTask
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri($this->baseUri . '/projection/' . urlencode($this->name) . '/config')
        );

        $promise = $this->sendAsyncRequest($request);

        return new GetProjectionConfigTask($promise, function (ResponseInterface $response): ProjectionConfig {
            switch ($response->getStatusCode()) {
                case 200:
                    $json = json_decode($response->getBody()->getContents());

                    return new ProjectionConfig(
                        $json['emitEnabled'],
                        $json['checkpointAfterMs'] ?? 0,
                        $json['checkpointHandledThreshold'],
                        $json['checkpointUnhandledBytesThreshold'],
                        $json['pendingEventsThreshold'],
                        $json['maxWriteBatchLength']
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
