<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\Http\RequestMethod;

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
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        string $groupName,
        int $amount,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->groupName = $groupName;
        $this->amount = $amount;
    }

    /**
     * @return RecordedEvent[]
     */
    public function __invoke(): array
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

        $response = $this->sendRequest($request);

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
                }

                return $events;
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
