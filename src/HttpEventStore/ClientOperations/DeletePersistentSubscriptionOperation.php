<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\PersistentSubscriptionDeleteResult;
use Prooph\EventStore\Internal\PersistentSubscriptionDeleteStatus;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal  */
class DeletePersistentSubscriptionOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var string */
    private $groupName;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        string $groupName,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->groupName = $groupName;
    }

    public function __invoke(): PersistentSubscriptionDeleteResult
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Delete,
            $this->uriFactory->createUri($this->baseUri . '/subscriptions/' . urlencode($this->stream) . '/' . urlencode($this->groupName))
        );

        $response = $this->sendRequest($request);

        $json = json_decode($response->getBody()->getContents(), true);
        switch ($response->getStatusCode()) {
            case 401:
                throw AccessDenied::toSubscription($this->stream, $this->groupName);
            case 200:
            case 404:
                return new PersistentSubscriptionDeleteResult(
                    $json['correlationId'],
                    $json['reason'],
                    PersistentSubscriptionDeleteStatus::byName($json['result'])
                );
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
