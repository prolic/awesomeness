<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventReadStatus;
use Prooph\EventStore\Internal\DateTimeFactory;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\Task\EventReadResultTask;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreHttpClient\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class ReadEventOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var int */
    private $eventNumber;

    /** @internal */
    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        int $eventNumber,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->eventNumber = $eventNumber;
    }

    public function task(): EventReadResultTask
    {
        $headers = [
            'Accept' => 'application/vnd.eventstore.atom+json',
        ];

        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri($this->baseUri . '/streams/' . urlencode($this->stream) . '/' . $this->eventNumber . '?embed=body'),
            $headers
        );

        $promise = $this->sendAsyncRequest($request);

        return new EventReadResultTask($promise, function (ResponseInterface $response): EventReadResult {
            switch ($response->getStatusCode()) {
                case 401:
                    return new EventReadResult(EventReadStatus::accessDenied(), $this->stream, $this->eventNumber, null);
                case 404:
                    return new EventReadResult(EventReadStatus::notFound(), $this->stream, $this->eventNumber, null);
                case 200:
                    $json = json_decode($response->getBody()->getContents(), true);

                    $event = new RecordedEvent(
                        $json['id'],
                        $json['eventId'],
                        $json['eventNumber'],
                        $json['eventType'],
                        $json['data'],
                        $json['metadata'],
                        $json['isJson'],
                        DateTimeFactory::create($json['updated'])
                    );

                    return new EventReadResult(EventReadStatus::success(), $this->stream, $this->eventNumber, $event);
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
