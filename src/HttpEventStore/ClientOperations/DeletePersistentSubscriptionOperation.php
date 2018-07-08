<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\PersistentSubscriptionDeleteResult;
use Prooph\EventStore\Internal\PersistentSubscriptionDeleteStatus;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal  */
class DeletePersistentSubscriptionOperation extends Operation
{
    public function __invoke(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        string $groupName,
        ?UserCredentials $userCredentials
    ): PersistentSubscriptionDeleteResult {
        $request = $requestFactory->createRequest(
            RequestMethod::Delete,
            $uriFactory->createUri($baseUri . '/subscriptions/' . \urlencode($stream) . '/' . \urlencode($groupName))
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

        $json = \json_decode($response->getBody()->getContents(), true);
        switch ($response->getStatusCode()) {
            case 401:
                throw AccessDenied::toSubscription($stream, $groupName);
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
