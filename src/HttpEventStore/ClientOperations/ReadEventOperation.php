<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\EventId;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventReadStatus;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class ReadEventOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var int */
    private $eventNumber;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        int $eventNumber,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->eventNumber = $eventNumber;
    }

    public function __invoke(): EventReadResult
    {
        $headers = [
            'Accept' => 'application/vnd.eventstore.atom+json',
        ];

        if (-1 === $this->eventNumber) {
            $request = $this->requestFactory->createRequest(
                RequestMethod::Get,
                $this->uriFactory->createUri($this->baseUri . '/streams/' . urlencode($this->stream) . '/head?embed=tryharder'),
                $headers
            );
        } else {
            $request = $this->requestFactory->createRequest(
                RequestMethod::Get,
                $this->uriFactory->createUri($this->baseUri . '/streams/' . urlencode($this->stream) . '/' . $this->eventNumber . '?embed=tryharder'),
                $headers
            );
        }

        $response = $this->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 401:
                throw AccessDenied::toStream($this->stream);
            case 404:
                return new EventReadResult(EventReadStatus::notFound(), $this->stream, $this->eventNumber, null);
            case 410:
                return new EventReadResult(EventReadStatus::streamDeleted(), $this->stream, $this->eventNumber, null);
            case 200:
                $json = json_decode($response->getBody()->getContents(), true);

                if (empty($json)) {
                    return new EventReadResult(EventReadStatus::notFound(), $this->stream, $this->eventNumber, null);
                }

                $data = $json['data'] ?? '';

                if (is_array($data)) {
                    $data = json_encode($data);
                }

                $field = isset($json['isLinkMetaData']) && $json['isLinkMetaData'] ? 'linkMetaData' : 'metaData';

                $metadata = $json[$field] ?? '';

                if (is_array($metadata)) {
                    $metadata = json_encode($metadata);
                }

                $event = new RecordedEvent(
                    $json['positionStreamId'],
                    EventId::fromString($json['eventId']),
                    $json['positionEventNumber'],
                    $json['eventType'],
                    $data,
                    $metadata,
                    $json['isJson'],
                    DateTimeUtil::create($json['updated'])
                );

                return new EventReadResult(EventReadStatus::success(), $this->stream, $this->eventNumber, $event);
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
