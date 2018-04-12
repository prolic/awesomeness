<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\WriteResult;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class AppendToStreamOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var int */
    private $expectedVersion;
    /** @var EventData[] */
    private $events;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        int $expectedVersion,
        array $events,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->expectedVersion = $expectedVersion;
        $this->events = $events;
    }

    public function __invoke(): WriteResult
    {
        $data = [];

        foreach ($this->events as $event) {
            $data[] = [
                'eventId' => $event->eventId()->toString(),
                'eventType' => $event->eventType(),
                'data' => $event->data(),
                'metadata' => $event->metaData(),
            ];
        }

        $string = json_encode($data);

        $request = $this->requestFactory->createRequest(
            RequestMethod::Post,
            $this->uriFactory->createUri($this->baseUri . '/streams/' . urlencode($this->stream)),
            [
                'Content-Type' => 'application/vnd.eventstore.events+json',
                'Content-Length' => strlen($string),
                'ES-ExpectedVersion' => $this->expectedVersion,
            ],
            $string
        );

        $response = $this->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 400:
                $header = $response->getHeader('ES-CurrentVersion');

                if (empty($header)) {
                    throw WrongExpectedVersion::withExpectedVersion($this->stream, $this->expectedVersion);
                }

                $currentVersion = (int) $header[0];

                throw WrongExpectedVersion::withCurrentVersion($this->stream, $this->expectedVersion, $currentVersion);
            case 401:
                throw AccessDenied::toStream($this->stream);
            case 410:
                throw StreamDeleted::with($this->stream);
            case 201:
                return new WriteResult();
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
