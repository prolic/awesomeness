<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Http\Promise\FulfilledPromise;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Task;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreHttpClient\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class AckOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var string */
    private $groupName;
    /** @var EventId[] */
    private $eventIds;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        string $groupName,
        iterable $eventIds,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->groupName = $groupName;
        $this->eventIds = $eventIds;
    }

    public function task(): Task
    {
        $eventIds = array_map(function (EventId $eventId): string {
            return $eventId->toString();
        }, $this->eventIds);

        var_dump($eventIds);

        $request = $this->requestFactory->createRequest(
            RequestMethod::Post,
            $this->uriFactory->createUri($this->baseUri . '/subscriptions/' . urlencode($this->stream) . '/' . urlencode($this->groupName) . '/ack?ids=' . implode(',', $eventIds))
        );

        $promise = $this->sendAsyncRequest($request);

        return new Task($promise, function (ResponseInterface $response): Task {
            switch ($response->getStatusCode()) {
                case 401:
                    throw AccessDenied::toStream($this->stream);
                case 202:
                    return new Task(new FulfilledPromise(null));
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
