<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\DateTimeFactory;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\Task\ReadFromSubscriptionTask;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class ReadFromSubscriptionOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var string */
    private $groupName;
    /** @var int */
    private $amount;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        string $groupName,
        int $amount,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->groupName = $groupName;
        $this->amount = $amount;
    }

    public function task(): ReadFromSubscriptionTask
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri(sprintf(
                '%s/subscriptions/%s/%s/%d?embed=tryharder',
                $this->baseUri,
                urlencode($this->stream),
                urlencode($this->groupName),
                $this->amount
            )),
            [
                'Accept' => 'application/vnd.eventstore.competingatom+json',
            ]
        );

        $promise = $this->sendAsyncRequest($request);

        return new ReadFromSubscriptionTask($promise, function (ResponseInterface $response): array {
            switch ($response->getStatusCode()) {
                case 401:
                    throw AccessDenied::toStream($this->stream);
                case 404:
                    throw new \RuntimeException(sprintf(
                        'Subscription with stream \'%s\' and group name \'%s\' not found',
                        $this->stream,
                        $this->groupName
                    ));
                case 200:
                    $json = json_decode($response->getBody()->getContents(), true);
                    $events = [];

                    if (null === $json) {
                        return $events;
                    }

                    foreach (array_reverse($json['entries']) as $entry) {
                        $data = $entry['data'] ?? '';

                        if (is_array($data)) {
                            $data = json_encode($data);
                        }

                        $metadata = $entry['metadata'] ?? '';

                        if (is_array($metadata)) {
                            $metadata = json_encode($metadata);
                        }

                        $events[] = new RecordedEvent(
                            $entry['id'],
                            EventId::fromString($entry['eventId']),
                            $entry['eventNumber'],
                            $entry['eventType'],
                            $data,
                            $metadata,
                            $entry['isJson'],
                            DateTimeFactory::create($entry['updated'])
                        );
                    }

                    return $events;
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
