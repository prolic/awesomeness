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

    /** @internal */
    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->start = $start;
        $this->count = $count;
        $this->resolveLinkTos = $resolveLinkTos;
    }

    public function task(): StreamEventsSliceTask
    {
        $headers = [
            'Accept' => 'application/vnd.eventstore.atom+json',
        ];

        if ($this->resolveLinkTos) {
            $resolve = '?embed=body';
        } else {
            $resolve = '';
        }

        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri(
                $this->baseUri . '/streams/' . urlencode($this->stream) . '/' . $this->start . '/backward/' . $this->count . $resolve
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
                        $data = $json['data'] ?? '';

                        if (is_array($data)) {
                            $data = json_encode($data);
                        }

                        $metadata = $json['metadata'] ?? '';

                        if (is_array($metadata)) {
                            $metadata = json_encode($metadata);
                        }

                        $events[] = new RecordedEvent(
                            $entry['id'],
                            $entry['eventId'],
                            $entry['eventNumber'],
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
        });
    }
}
