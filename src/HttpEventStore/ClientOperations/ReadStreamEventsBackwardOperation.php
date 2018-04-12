<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\DateTimeFactory;
use Prooph\EventStore\ReadDirection;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class ReadStreamEventsBackwardOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var int */
    private $start;
    /** @var int */
    private $count;
    /** @var bool */
    private $resolveLinkTos;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->start = $start;
        $this->count = $count;
        $this->resolveLinkTos = $resolveLinkTos;
    }

    public function __invoke(): StreamEventsSlice
    {
        $headers = [
            'Accept' => 'application/vnd.eventstore.atom+json',
        ];

        if (! $this->resolveLinkTos) {
            $headers['ES-ResolveLinkTos'] = 'false';
        }

        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri(
                $this->baseUri . '/streams/' . urlencode($this->stream) . '/' . $this->start . '/backward/' . $this->count . '?embed=tryharder'
            ),
            $headers
        );

        $response = $this->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 401:
                throw AccessDenied::toStream($this->stream);
            case 404:
                return new StreamEventsSlice(
                    SliceReadStatus::streamNotFound(),
                    $this->stream,
                    $this->start,
                    ReadDirection::backward(),
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
                    ReadDirection::backward(),
                    [],
                    0,
                    0,
                    true
                );
            case 200:
                $json = json_decode($response->getBody()->getContents(), true);

                $events = [];
                $lastEventNumber = 0;
                foreach ($json['entries'] as $entry) {
                    $data = $entry['data'] ?? '';

                    if (is_array($data)) {
                        $data = json_encode($data);
                    }

                    $field = isset($json['isLinkMetaData']) && $json['isLinkMetaData'] ? 'linkMetaData' : 'metaData';

                    $metadata = $json[$field] ?? '';

                    if (is_array($metadata)) {
                        $metadata = json_encode($metadata);
                    }

                    $events[] = new RecordedEvent(
                        $entry['positionStreamId'],
                        EventId::fromString($entry['eventId']),
                        $entry['positionEventNumber'],
                        $entry['eventType'],
                        $data,
                        $metadata,
                        $entry['isJson'],
                        DateTimeFactory::create($entry['updated'])
                    );
                    $lastEventNumber = $entry['eventNumber'];
                }
                $nextEventNumber = ($lastEventNumber < 1) ? 0 : ($lastEventNumber - 1);

                return new StreamEventsSlice(
                    SliceReadStatus::success(),
                    $this->stream,
                    $this->start,
                    ReadDirection::backward(),
                    $events,
                    $nextEventNumber,
                    $lastEventNumber,
                    false
                );
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
