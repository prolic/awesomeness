<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\ReadDirection;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class ReadStreamEventsForwardOperation extends Operation
{
    public function __invoke(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ): StreamEventsSlice {
        $headers = [
            'Accept' => 'application/vnd.eventstore.atom+json',
        ];

        if (! $resolveLinkTos) {
            $headers['ES-ResolveLinkTos'] = 'false';
        }

        $request = $requestFactory->createRequest(
            RequestMethod::Get,
            $uriFactory->createUri(
                $baseUri . '/streams/' . urlencode($stream) . '/' . $start . '/forward/' . $count . '?embed=tryharder'
            ),
            $headers
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

        switch ($response->getStatusCode()) {
            case 401:
                throw AccessDenied::toStream($stream);
            case 404:
                return new StreamEventsSlice(
                    SliceReadStatus::streamNotFound(),
                    $stream,
                    $start,
                    ReadDirection::forward(),
                    [],
                    0,
                    0,
                    true
                );
            case 410:
                return new StreamEventsSlice(
                    SliceReadStatus::streamDeleted(),
                    $stream,
                    $start,
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
                        DateTimeUtil::create($entry['updated'])
                    );
                    $lastEventNumber = $entry['eventNumber'];
                }

                return new StreamEventsSlice(
                    SliceReadStatus::success(),
                    $stream,
                    $start,
                    ReadDirection::forward(),
                    $events,
                    $lastEventNumber + 1,
                    $lastEventNumber,
                    $json['headOfStream']
                );
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
