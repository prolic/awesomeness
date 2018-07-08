<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\Data\EventId;
use Prooph\EventStore\Data\EventReadResult;
use Prooph\EventStore\Data\EventReadStatus;
use Prooph\EventStore\Data\EventRecord;
use Prooph\EventStore\Data\ResolvedEvent;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class ReadEventOperation extends Operation
{
    public function __invoke(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        int $eventNumber,
        bool $resolveLinkTo,
        ?UserCredentials $userCredentials
    ): EventReadResult {
        $headers = [
            'Accept' => 'application/vnd.eventstore.atom+json',
        ];

        if (-1 === $eventNumber) {
            $request = $requestFactory->createRequest(
                RequestMethod::Get,
                $uriFactory->createUri($baseUri . '/streams/' . \urlencode($stream) . '/head?embed=tryharder'),
                $headers
            );
        } else {
            $request = $requestFactory->createRequest(
                RequestMethod::Get,
                $uriFactory->createUri($baseUri . '/streams/' . \urlencode($stream) . '/' . $eventNumber . '?embed=tryharder'),
                $headers
            );
        }

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

        switch ($response->getStatusCode()) {
            case 401:
                throw AccessDenied::toStream($stream);
            case 404:
                return new EventReadResult(EventReadStatus::notFound(), $stream, $eventNumber, null);
            case 410:
                return new EventReadResult(EventReadStatus::streamDeleted(), $stream, $eventNumber, null);
            case 200:
                $json = \json_decode($response->getBody()->getContents(), true);

                if (empty($json)) {
                    return new EventReadResult(EventReadStatus::notFound(), $stream, $eventNumber, null);
                }

                if ($resolveLinkTo && $json['streamId'] !== $stream) {
                    $data = $json['data'] ?? '';

                    if (\is_array($data)) {
                        $data = \json_encode($data);
                    }

                    $field = isset($json['isLinkMetaData']) && $json['isLinkMetaData'] ? 'linkMetaData' : 'metaData';

                    $metadata = $json[$field] ?? '';

                    if (\is_array($metadata)) {
                        $metadata = \json_encode($metadata);
                    }

                    $link = new EventRecord(
                        $stream,
                        $json['positionEventNumber'],
                        EventId::fromString($json['eventId']),
                        $json['eventType'],
                        $json['isJson'],
                        $data,
                        $metadata,
                        DateTimeUtil::create($json['updated'])
                    );
                } else {
                    $link = null;
                }

                $record = new EventRecord(
                    $json['streamId'],
                    $json['eventNumber'],
                    EventId::fromString($json['eventId']),
                    SystemEventTypes::LinkTo,
                    false,
                    $json['title'],
                    '',
                    DateTimeUtil::create($json['updated'])
                );

                $event = new ResolvedEvent($record, $link, null);

                return new EventReadResult(EventReadStatus::success(), $stream, $eventNumber, $event);
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
