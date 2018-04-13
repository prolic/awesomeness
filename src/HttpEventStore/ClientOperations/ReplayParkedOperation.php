<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\ReplayParkedResult;
use Prooph\EventStore\Internal\ReplayParkedStatus;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class ReplayParkedOperation extends Operation
{
    public function __invoke(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        string $groupName,
        ?UserCredentials $userCredentials
    ): ReplayParkedResult {
        $request = $requestFactory->createRequest(
            RequestMethod::Post,
            $uriFactory->createUri(sprintf(
                '%s/subscriptions/%s/%s/replayParked',
                $baseUri,
                urlencode($stream),
                urlencode($groupName)
            )),
            [
                'Content-Length' => 0,
            ],
            ''
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

        switch ($response->getStatusCode()) {
            case 401:
                throw AccessDenied::toStream($stream);
            case 404:
            case 200:
                $json = json_decode($response->getBody()->getContents(), true);

                return new ReplayParkedResult(
                    $json['correlationId'],
                    $json['reason'],
                    ReplayParkedStatus::byName($json['result'])
                );
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
