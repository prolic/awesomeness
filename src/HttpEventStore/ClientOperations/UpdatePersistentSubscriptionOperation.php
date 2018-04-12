<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\PersistentSubscriptionUpdateResult;
use Prooph\EventStore\Internal\PersistentSubscriptionUpdateStatus;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal  */
class UpdatePersistentSubscriptionOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var string */
    private $groupName;
    /** @var PersistentSubscriptionSettings */
    private $settings;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->groupName = $groupName;
        $this->settings = $settings;
    }

    public function __invoke(): PersistentSubscriptionUpdateResult
    {
        $string = json_encode($this->settings->toArray());

        $request = $this->requestFactory->createRequest(
            RequestMethod::Post,
            $this->uriFactory->createUri($this->baseUri . '/subscriptions/' . urlencode($this->stream) . '/' . urlencode($this->groupName)),
            [
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($string),
            ],
            $string
        );

        $response = $this->sendRequest($request);

        $json = json_decode($response->getBody()->getContents(), true);
        switch ($response->getStatusCode()) {
            case 401:
                throw AccessDenied::toSubscription($this->stream, $this->groupName);
            case 200:
            case 404:
                return new PersistentSubscriptionUpdateResult(
                    $json['correlationId'],
                    $json['reason'],
                    PersistentSubscriptionUpdateStatus::byName($json['result'])
                );

            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
