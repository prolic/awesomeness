<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\CreatePersistentSubscriptionResult;
use Prooph\EventStore\CreatePersistentSubscriptionStatus;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\Task\CreatePersistentSubscriptionTask;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreHttpClient\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal  */
class CreatePersistentSubscriptionOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var string */
    private $groupName;
    /** @var PersistentSubscriptionSettings */
    private $settings;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->groupName = $groupName;
        $this->settings = $settings;
    }

    public function task(): CreatePersistentSubscriptionTask
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Put,
            $this->uriFactory->createUri($this->baseUri . '/subscriptions/' . urlencode($this->stream) . '/' . urlencode($this->groupName)),
            [
                'Content-Type' => 'application/json',
            ],
            json_encode($this->settings->toArray())
        );

        $promise = $this->sendAsyncRequest($request);

        return new CreatePersistentSubscriptionTask($promise, function (ResponseInterface $response): CreatePersistentSubscriptionResult {
            $json = json_decode($response->getBody()->getContents(), true);
            switch ($response->getStatusCode()) {
                case 401:
                    throw AccessDenied::toSubscription($this->stream, $this->groupName);
                case 201:
                case 409:
                    return new CreatePersistentSubscriptionResult(
                        $json['correlationId'],
                        $json['reason'],
                        CreatePersistentSubscriptionStatus::byName($json['result'])
                    );
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
