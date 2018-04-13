<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\PersistentSubscriptionCreateResult;
use Prooph\EventStore\Internal\PersistentSubscriptionCreateStatus;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal  */
class CreatePersistentSubscriptionOperation extends Operation
{
    public function __invoke(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials
    ): PersistentSubscriptionCreateResult {
        $string = json_encode($settings->toArray());

        $request = $requestFactory->createRequest(
            RequestMethod::Put,
            $uriFactory->createUri($baseUri . '/subscriptions/' . urlencode($stream) . '/' . urlencode($groupName)),
            [
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($string),
            ],
            $string
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

        $json = json_decode($response->getBody()->getContents(), true);
        switch ($response->getStatusCode()) {
            case 401:
                throw AccessDenied::toSubscription($stream, $groupName);
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
    }
}
