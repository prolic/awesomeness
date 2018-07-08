<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Data\PersistentSubscriptionSettings;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\PersistentSubscriptionUpdateResult;
use Prooph\EventStore\Internal\PersistentSubscriptionUpdateStatus;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal  */
class UpdatePersistentSubscriptionOperation extends Operation
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
    ): PersistentSubscriptionUpdateResult {
        $string = \json_encode($settings->toArray());

        $request = $requestFactory->createRequest(
            RequestMethod::Post,
            $uriFactory->createUri($baseUri . '/subscriptions/' . \urlencode($stream) . '/' . \urlencode($groupName)),
            [
                'Content-Type' => 'application/json',
                'Content-Length' => \strlen($string),
            ],
            $string
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

        $json = \json_decode($response->getBody()->getContents(), true);
        switch ($response->getStatusCode()) {
            case 200:
                return new PersistentSubscriptionUpdateResult(
                    $json['correlationId'],
                    '',
                    PersistentSubscriptionUpdateStatus::success()
                );
            case 401:
                throw AccessDenied::toSubscription($stream, $groupName);
            case 404:
                return new PersistentSubscriptionUpdateResult(
                    $json['correlationId'],
                    $json['reason'],
                    PersistentSubscriptionUpdateStatus::notFound()
                );
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
