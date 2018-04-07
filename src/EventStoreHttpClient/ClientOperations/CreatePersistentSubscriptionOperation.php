<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStoreClient\Exception\AccessDenied;
use Prooph\EventStoreClient\Internal\PersistentSubscriptionCreateResult;
use Prooph\EventStoreClient\Internal\PersistentSubscriptionCreateStatus;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Prooph\EventStoreClient\Task\CreatePersistentSubscriptionTask;
use Prooph\EventStoreClient\UserCredentials;
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

        return new CreatePersistentSubscriptionTask($promise, function (ResponseInterface $response): PersistentSubscriptionCreateResult {
            $json = json_decode($response->getBody()->getContents(), true);
            switch ($response->getStatusCode()) {
                case 401:
                    throw AccessDenied::toSubscription($this->stream, $this->groupName);
                case 201:
                case 409:
                    return new PersistentSubscriptionCreateResult(
                        $json['correlationId'],
                        $json['reason'],
                        PersistentSubscriptionCreateStatus::byName($json['result'])
                    );
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
