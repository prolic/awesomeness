<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ProjectionManagement\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\ProjectionNotFound;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class DisableOperation extends Operation
{
    public function __invoke(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $name,
        ?UserCredentials $userCredentials
    ): void {
        $request = $requestFactory->createRequest(
            RequestMethod::Post,
            $uriFactory->createUri($baseUri . '/projection/' . urlencode($name) . '/command/disable')
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

        switch ($response->getStatusCode()) {
            case 200:
                return;
            case 401:
                throw AccessDenied::toProjection($name);
            case 404:
                throw ProjectionNotFound::withName($name);
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
