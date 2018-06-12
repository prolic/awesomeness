<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\DetailedSubscriptionInformation;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\NamedConsumerStrategy;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class GetInformationForSubscriptionOperation extends Operation
{
    public function __invoke(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        string $groupName,
        ?UserCredentials $userCredentials
    ): DetailedSubscriptionInformation {
        $request = $requestFactory->createRequest(
            RequestMethod::Get,
            $uriFactory->createUri($baseUri . '/subscriptions/' . \urlencode($stream) . '/' . \urlencode($groupName) . '/info')
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

        switch ($response->getStatusCode()) {
            case 200:
                $json = \json_decode($response->getBody()->getContents(), true);

                return new DetailedSubscriptionInformation(
                    new PersistentSubscriptionSettings(
                        $json['config']['resolveLinktos'],
                        $json['config']['startFrom'],
                        $json['config']['extraStatistics'],
                        $json['config']['checkPointAfterMilliseconds'],
                        $json['config']['liveBufferSize'],
                        $json['config']['readBatchSize'],
                        $json['config']['bufferSize'],
                        $json['config']['maxCheckPointCount'],
                        $json['config']['maxRetryCount'],
                        $json['config']['maxSubscriberCount'],
                        $json['config']['messageTimeoutMilliseconds'],
                        $json['config']['minCheckPointCount'],
                        NamedConsumerStrategy::byName($json['config']['namedConsumerStrategy'])
                    ),
                    $json['eventStreamId'],
                    $json['groupName'],
                    $json['status'],
                    $json['averageItemsPerSecond'],
                    $json['totalItemsProcessed'],
                    $json['countSinceLastMeasurement'],
                    $json['lastProcessedEventNumber'],
                    $json['lastKnownEventNumber'],
                    $json['readBufferCount'],
                    $json['liveBufferCount'],
                    $json['retryBufferCount'],
                    $json['totalInFlightMessages']
                );
            case 401:
                throw new AccessDenied();
            case 404:
                throw new \RuntimeException(\sprintf(
                    'Subscription with stream \'%s\' and group name \'%s\' not found',
                    $stream,
                    $groupName
                ));
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
