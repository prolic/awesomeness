<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ProjectionManagement\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\ProjectionNotFound;
use Prooph\EventStore\ProjectionManagement\ProjectionConfig;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class GetProjectionConfigOperation extends Operation
{
    public function __invoke(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $name,
        ?UserCredentials $userCredentials
    ): ProjectionConfig {
        $request = $requestFactory->createRequest(
            RequestMethod::Get,
            $uriFactory->createUri($baseUri . '/projection/' . \urlencode($name) . '/config')
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

        switch ($response->getStatusCode()) {
            case 200:
                $json = \json_decode($response->getBody()->getContents(), true);

                return new ProjectionConfig(
                    $json['emitEnabled'],
                    $json['checkpointAfterMs'] ?? 0,
                    $json['checkpointHandledThreshold'],
                    $json['checkpointUnhandledBytesThreshold'],
                    $json['pendingEventsThreshold'],
                    $json['maxWriteBatchLength']
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
