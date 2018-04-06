<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\PersistentSubscriptionNakEventAction;
use Prooph\EventStore\Task;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreHttpClient\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class NackOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var string */
    private $groupName;
    /** @var EventId[] */
    private $eventIds;
    /** @var PersistentSubscriptionNakEventAction */
    private $action;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        string $groupName,
        array $eventIds,
        PersistentSubscriptionNakEventAction $action,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->groupName = $groupName;
        $this->eventIds = $eventIds;
        $this->action = $action;
    }

    public function task(): Task
    {
        $eventIds = array_map(function (EventId $eventId): string {
            return $eventId->toString();
        }, $this->eventIds);

        $request = $this->requestFactory->createRequest(
            RequestMethod::Post,
            $this->uriFactory->createUri(sprintf(
                '%s/subscriptions/%s/%s/nack?ids=%s&action=%s',
                $this->baseUri,
                urlencode($this->stream),
                urlencode($this->groupName),
                implode(',', $eventIds),
                $this->action->name()
            )),
            [
                'Content-Length' => 0,
            ],
            ''
        );

        $promise = $this->sendAsyncRequest($request);

        return new Task($promise, function (ResponseInterface $response): void {
            switch ($response->getStatusCode()) {
                case 401:
                    throw AccessDenied::toStream($this->stream);
                case 202:
                    return;
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
