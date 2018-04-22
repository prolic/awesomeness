<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ProjectionManagement\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\ProjectionManagement\ProjectionDetails;
use Prooph\EventStore\ProjectionManagement\ProjectionNotFound;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class GetProjectionDetailsOperation extends Operation
{
    public function __invoke(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $name,
        ?UserCredentials $userCredentials
    ): ProjectionDetails {
        $request = $requestFactory->createRequest(
            RequestMethod::Get,
            $uriFactory->createUri($baseUri . '/projection/' . urlencode($name))
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

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
                throw AccessDenied::toProjection($name);
            case 404:
                throw ProjectionNotFound::withName($name);
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
