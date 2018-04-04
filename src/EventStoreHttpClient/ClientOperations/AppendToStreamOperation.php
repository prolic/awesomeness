<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\Task\WriteResultTask;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\WriteResult;
use Prooph\EventStoreHttpClient\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class AppendToStreamOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var int */
    private $expectedVersion;
    /** @var EventData[] */
    private $events;

    /** @internal */
    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        int $expectedVersion,
        iterable $events,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->expectedVersion = $expectedVersion;
        $this->events = $events;
    }

    public function task(): WriteResultTask
    {
        $data = [];
        foreach ($this->events as $event) {
            $data[] = [
                'eventId' => $event->eventId()->toString(),
                'eventType' => $event->type(),
                'data' => $event->data(),
                'metadata' => $event->metadata(),
            ];
        }
        $request = $this->requestFactory->createRequest(
            RequestMethod::Post,
            $this->uriFactory->createUri($this->baseUri . '/streams/' . urlencode($this->stream)),
            [
                'Content-Type' => 'application/vnd.eventstore.events+json',
                'ES-ExpectedVersion' => $this->expectedVersion,
            ],
            json_encode($data)
        );

        $promise = $this->sendAsyncRequest($request);

        return new WriteResultTask($promise, function (ResponseInterface $response): WriteResult {
            switch ($response->getStatusCode()) {
                case 400:
                    $header = $response->getHeader('ES-CurrentVersion');

                    if (empty($header)) {
                        throw WrongExpectedVersion::withExpectedVersion($this->stream, $this->expectedVersion);
                    }
                        $currentVersion = (int) $header[0];

                    throw WrongExpectedVersion::withCurrentVersion($this->stream, $this->expectedVersion, $currentVersion);
                case 401:
                    throw AccessDenied::with($this->stream);
                case 410:
                    throw StreamDeleted::with($this->stream);
                case 201:
                    $nextExpectedVersion = $this->expectedVersion + count($this->events) + 1;

                    return new WriteResult($nextExpectedVersion);
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
