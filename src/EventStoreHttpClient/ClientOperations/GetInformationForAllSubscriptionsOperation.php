<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStoreClient\Exception\AccessDenied;
use Prooph\EventStoreClient\SubscriptionInformation;
use Prooph\EventStoreClient\Task\GetInformationForSubscriptionsTask;
use Prooph\EventStoreClient\UserCredentials;
use Prooph\EventStoreHttpClient\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class GetInformationForAllSubscriptionsOperation extends Operation
{
    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);
    }

    public function task(): GetInformationForSubscriptionsTask
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri($this->baseUri . '/subscriptions')
        );

        $promise = $this->sendAsyncRequest($request);

        return new GetInformationForSubscriptionsTask($promise, function (ResponseInterface $response): array {
            switch ($response->getStatusCode()) {
                case 401:
                    throw new AccessDenied();
                case 200:
                    $json = json_decode($response->getBody()->getContents(), true);

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
        });
    }
}
