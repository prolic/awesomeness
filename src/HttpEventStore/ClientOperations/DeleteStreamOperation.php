<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class DeleteStreamOperation extends Operation
{
    public function __invoke(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        bool $hardDelete,
        ?UserCredentials $userCredentials
    ): void {
        $headers = [];

        if ($hardDelete) {
            $headers['ES-HardDelete'] = 'true';
        }

        $request = $requestFactory->createRequest(
            RequestMethod::Delete,
            $uriFactory->createUri($baseUri . '/streams/' . urlencode($stream)),
            $headers
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

        switch ($response->getStatusCode()) {
            case 204:
            case 410:
                return;
            case 401:
                throw AccessDenied::toStream($stream);
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
