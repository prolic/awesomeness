<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\SubscriptionInformation;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class GetInformationForSubscriptionsWithStreamOperation extends Operation
{
    /**
     * @return SubscriptionInformation[]
     */
    public function __invoke(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        ?UserCredentials $userCredentials
    ): array {
        $request = $requestFactory->createRequest(
            RequestMethod::Get,
            $uriFactory->createUri($baseUri . '/subscriptions/' . \urlencode($stream))
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

        switch ($response->getStatusCode()) {
            case 401:
                throw new AccessDenied();
            case 200:
                $json = \json_decode($response->getBody()->getContents(), true);

                $result = [];

                foreach ($json as $entry) {
                    $result[] = new SubscriptionInformation(
                        $entry['eventStreamId'],
                        $entry['groupName'],
                        $entry['status'],
                        $entry['averageItemsPerSecond'],
                        $entry['totalItemsProcessed'],
                        $entry['lastProcessedEventNumber'],
                        $entry['lastKnownEventNumber'],
                        $entry['connectionCount'],
                        $entry['totalInFlightMessages']
                    );
                }

                return $result;
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
