<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\DateTimeFactory;
use Prooph\EventStore\ReadDirection;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\Task\StreamEventsSliceTask;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreHttpClient\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class ReadStreamEventsForwardOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var int */
    private $start;
    /** @var int */
    private $count;

    /** @internal */
    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        int $start,
        int $count,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->start = $start;
        $this->count = $count;
    }

    public function task(): StreamEventsSliceTask
    {
        $headers = [
            'Accept' => 'application/vnd.eventstore.atom+json',
        ];

        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri(
                $this->baseUri . '/streams/' . urlencode($this->stream) . '/' . $this->start . '/forward/' . $this->count . '?embed=body'
            ),
            $headers
        );

        $promise = $this->sendAsyncRequest($request);

        return new StreamEventsSliceTask($promise, function (ResponseInterface $response): StreamEventsSlice {
            switch ($response->getStatusCode()) {
                case 401:
                    throw AccessDenied::with($this->stream);
                case 404:
                    return new StreamEventsSlice(
                        SliceReadStatus::streamNotFound(),
                        $this->stream,
                        $this->start,
                        ReadDirection::forward(),
                        [],
                        0,
                        0,
                        true
                    );
                case 410:
                    return new StreamEventsSlice(
                        SliceReadStatus::streamDeleted(),
                        $this->stream,
                        $this->start,
                        ReadDirection::forward(),
                        [],
                        0,
                        0,
                        true
                    );
                case 200:
                    $json = json_decode($response->getBody()->getContents(), true);

                    $events = [];
                    $lastEventNumber = 0;
                    foreach (array_reverse($json['entries']) as $entry) {
                        $events[] = new RecordedEvent(
                            $entry['id'],
                            $entry['eventId'],
                            $entry['eventNumber'],
                            $entry['eventType'],
                            $entry['data'],
                            $entry['metadata'] ?? '',
                            $entry['isJson'],
                            DateTimeFactory::create($entry['updated'])
                        );
                        $lastEventNumber = $entry['eventNumber'];
                    }

                    return new StreamEventsSlice(
                        SliceReadStatus::success(),
                        $this->stream,
                        $this->start,
                        ReadDirection::forward(),
                        $events,
                        $lastEventNumber + 1,
                        $lastEventNumber,
                        $json['headOfStream']
                    );
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
