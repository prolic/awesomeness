<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\WriteResult;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class AppendToStreamOperation extends Operation
{
    public function __invoke(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        int $expectedVersion,
        array $events,
        ?UserCredentials $userCredentials
    ): WriteResult {
        $data = [];

        foreach ($events as $event) {
            $data[] = [
                'eventId' => $event->eventId()->toString(),
                'eventType' => $event->eventType(),
                'data' => $event->data(),
                'metadata' => $event->metaData(),
            ];
        }

        $string = json_encode($data);

        $request = $requestFactory->createRequest(
            RequestMethod::Post,
            $uriFactory->createUri($baseUri . '/streams/' . urlencode($stream)),
            [
                'Content-Type' => 'application/vnd.eventstore.events+json',
                'Content-Length' => strlen($string),
                'ES-ExpectedVersion' => $expectedVersion,
            ],
            $string
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

        switch ($response->getStatusCode()) {
            case 400:
                $header = $response->getHeader('ES-CurrentVersion');

                if (empty($header)) {
                    throw WrongExpectedVersion::withExpectedVersion($stream, $expectedVersion);
                }

                $currentVersion = (int) $header[0];

                throw WrongExpectedVersion::withCurrentVersion($stream, $expectedVersion, $currentVersion);
            case 401:
                throw AccessDenied::toStream($stream);
            case 410:
                throw StreamDeleted::with($stream);
            case 201:
                return new WriteResult();
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
