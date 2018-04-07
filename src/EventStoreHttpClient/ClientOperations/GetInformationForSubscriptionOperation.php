<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStoreClient\DetailedSubscriptionInformation;
use Prooph\EventStoreClient\Exception\AccessDenied;
use Prooph\EventStoreClient\NamedConsumerStrategy;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Prooph\EventStoreClient\Task\GetInformationForSubscriptionTask;
use Prooph\EventStoreClient\UserCredentials;
use Prooph\EventStoreHttpClient\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class GetInformationForSubscriptionOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var string */
    private $groupName;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        string $groupName,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->groupName = $groupName;
    }

    public function task(): GetInformationForSubscriptionTask
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri($this->baseUri . '/subscriptions/' . urlencode($this->stream) . '/' . urlencode($this->groupName) . '/info')
        );

        $promise = $this->sendAsyncRequest($request);

        return new GetInformationForSubscriptionTask($promise, function (ResponseInterface $response): DetailedSubscriptionInformation {
            switch ($response->getStatusCode()) {
                case 200:
                    $json = json_decode($response->getBody()->getContents(), true);

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
                    throw new \RuntimeException(sprintf(
                        'Subscription with stream \'%s\' and group name \'%s\' not found',
                        $this->stream,
                        $this->groupName
                    ));
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
