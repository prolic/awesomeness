<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Internal\PersistentSubscriptionOperations as BasePersistentSubscriptionOperations;
use Prooph\EventStore\PersistentSubscriptionNakEventAction;
use Prooph\EventStore\Task\ReadFromSubscriptionTask;
use Prooph\EventStore\UserCredentials;

/** @internal */
final class PersistentSubscriptionOperations extends Operation implements BasePersistentSubscriptionOperations
{
    /** @var string */
    private $stream;
    /** @var string */
    private $groupName;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        string $groupName,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->groupName = $groupName;
    }

    public function readFromSubscription(int $amount): ReadFromSubscriptionTask
    {
        $operation = new ReadFromSubscriptionOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $this->stream,
            $this->groupName,
            $amount,
            $this->userCredentials
        );

        return $operation->task();
    }

    public function acknowledge(array $eventIds): void
    {
        $operation = new AckOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $this->stream,
            $this->groupName,
            $eventIds,
            $this->userCredentials
        );

        $task = $operation->task();
        $task->result();
    }

    public function fail(array $eventIds, PersistentSubscriptionNakEventAction $action): void
    {
        $operation = new NackOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $this->stream,
            $this->groupName,
            $eventIds,
            $action,
            $this->userCredentials
        );

        $task = $operation->task();
        $task->result();
    }
}
