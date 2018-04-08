<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ProjectionManagement\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStoreClient\Exception\AccessDenied;
use Prooph\EventStoreClient\ProjectionManagement\ProjectionDetails;
use Prooph\EventStoreClient\Task\GetProjectionTask;
use Prooph\EventStoreClient\UserCredentials;
use Prooph\EventStoreHttpClient\ClientOperations\Operation;
use Prooph\EventStoreHttpClient\Http\RequestMethod;
use Prooph\EventStoreHttpClient\ProjectionManagement\ProjectionNotFound;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class GetOperation extends Operation
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

    public function task(): GetProjectionTask
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri($this->baseUri . '/projection/' . urlencode($this->name))
        );

        $promise = $this->sendAsyncRequest($request);

        return new GetProjectionTask($promise, function (ResponseInterface $response): ProjectionDetails {
            switch ($response->getStatusCode()) {
                case 200:
                    $json = json_decode($response->getBody()->getContents(), true);

                    return new ProjectionDetails(
                        $json['coreProcessingTime'],
                        $json['version'],
                        $json['epoch'],
                        $json['effectiveName'],
                        $json['writesInProgress'],
                        $json['readsInProgress'],
                        $json['partitionsCached'],
                        $json['status'],
                        $json['stateReason'],
                        $json['name'],
                        $json['mode'],
                        $json['position'],
                        $json['progress'],
                        $json['lastCheckpoint'],
                        $json['eventsProcessedAfterRestart'],
                        $json['statusUrl'],
                        $json['stateUrl'],
                        $json['resultUrl'],
                        $json['queryUrl'],
                        $json['enableCommandUrl'],
                        $json['disableCommandUrl'],
                        $json['checkpointStatus'],
                        $json['bufferedEvents'],
                        $json['writePendingEventsBeforeCheckpoint'],
                        $json['writePendingEventsAfterCheckpoint']
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
