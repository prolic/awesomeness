<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Http\Promise\FulfilledPromise;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\Common\SystemStreams;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventReadStatus;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\EventStoreSubscriptionConnection;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Internal\Consts;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\Position;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\StreamMetadataResult;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\Task;
use Prooph\EventStore\Task\AllEventsSliceTask;
use Prooph\EventStore\Task\CreatePersistentSubscriptionTask;
use Prooph\EventStore\Task\DeletePersistentSubscriptionTask;
use Prooph\EventStore\Task\DeleteResultTask;
use Prooph\EventStore\Task\EventReadResultTask;
use Prooph\EventStore\Task\StreamEventsSliceTask;
use Prooph\EventStore\Task\StreamMetadataResultTask;
use Prooph\EventStore\Task\UpdatePersistentSubscriptionTask;
use Prooph\EventStore\Task\WriteResultTask;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreHttpClient\ClientOperations\AppendToStreamOperation;
use Prooph\EventStoreHttpClient\ClientOperations\CreatePersistentSubscriptionOperation;
use Prooph\EventStoreHttpClient\ClientOperations\DeletePersistentSubscriptionOperation;
use Prooph\EventStoreHttpClient\ClientOperations\DeleteStreamOperation;
use Prooph\EventStoreHttpClient\ClientOperations\GetInformationForAllSubscriptionsOperation;
use Prooph\EventStoreHttpClient\ClientOperations\ReadEventOperation;
use Prooph\EventStoreHttpClient\ClientOperations\ReadStreamEventsBackwardOperation;
use Prooph\EventStoreHttpClient\ClientOperations\ReadStreamEventsForwardOperation;
use Prooph\EventStoreHttpClient\ClientOperations\UpdatePersistentSubscriptionOperation;
use Ramsey\Uuid\Uuid;

class EventStoreHttpConnection implements EventStoreConnection, EventStoreSubscriptionConnection
{
    /** @var HttpAsyncClient */
    private $asyncClient;
    /** @var RequestFactory */
    private $requestFactory;
    /** @var UriFactory */
    private $uriFactory;
    /** @var string */
    private $connectionName;
    /** @var ConnectionSettings */
    private $settings;
    /** @var string */
    private $baseUri;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        ConnectionSettings $settings = null,
        string $connectionName = null
    ) {
        $this->asyncClient = $asyncClient;
        $this->requestFactory = $requestFactory;
        $this->uriFactory = $uriFactory;
        $this->settings = $settings ?? ConnectionSettings::default();
        $this->connectionName = $connectionName ?? sprintf('ES-%s', Uuid::uuid4()->toString());
        $this->baseUri = sprintf(
            '%s://%s:%s',
            $this->settings->useSslConnection() ? 'https' : 'http',
            $this->settings->endPoint()->host(),
            $this->settings->endPoint()->port()
        );
    }

    public function connectionName(): string
    {
        return $this->connectionName;
    }

    public function connectAsync(): Task
    {
        return new Task(new FulfilledPromise(null));
    }

    public function close(): void
    {
        // do nothing
    }

    public function deleteStreamAsync(
        string $stream,
        bool $hardDelete,
        UserCredentials $userCredentials = null
    ): DeleteResultTask {
        if (empty($stream)) {
            throw new \InvalidArgumentException('Stream cannot be empty');
        }

        $operation = new DeleteStreamOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $stream,
            $hardDelete,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    /**
     * @param string $stream
     * @param int $expectedVersion
     * @param null|UserCredentials $userCredentials
     * @param EventData[] $events
     * @return WriteResultTask
     */
    public function appendToStreamAsync(
        string $stream,
        int $expectedVersion,
        iterable $events,
        UserCredentials $userCredentials = null
    ): WriteResultTask {
        if (empty($stream)) {
            throw new \InvalidArgumentException('Stream cannot be empty');
        }

        $operation = new AppendToStreamOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $stream,
            $expectedVersion,
            $events,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function readEventAsync(
        string $stream,
        int $eventNumber,
        UserCredentials $userCredentials = null
    ): EventReadResultTask {
        if ($eventNumber < -1) {
            throw new \InvalidArgumentException('EventNumber cannot be smaller then -1');
        }
        if (empty($stream)) {
            throw new \InvalidArgumentException('Stream cannot be empty');
        }

        $operation = new ReadEventOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $stream,
            $eventNumber,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function readStreamEventsForwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSliceTask {
        if (empty($stream)) {
            throw new \InvalidArgumentException('Stream cannot be empty');
        }

        if ($start < 0) {
            throw new \InvalidArgumentException('Start cannot be negative');
        }

        if ($count > Consts::MaxReadSize) {
            throw new \InvalidArgumentException(
                'Count should be less than ' . Consts::MaxReadSize . '. For larger reads you should page.'
            );
        }

        $operation = new ReadStreamEventsForwardOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $stream,
            $start,
            $count,
            $resolveLinkTos,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function readStreamEventsBackwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSliceTask {
        if (empty($stream)) {
            throw new \InvalidArgumentException('Stream cannot be empty');
        }

        if ($count > Consts::MaxReadSize) {
            throw new \InvalidArgumentException(
                'Count should be less than ' . Consts::MaxReadSize . '. For larger reads you should page.'
            );
        }

        $operation = new ReadStreamEventsBackwardOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $stream,
            $start,
            $count,
            $resolveLinkTos,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function readAllEventsForwardAsync(
        Position $position,
        int $maxCount,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): AllEventsSliceTask {
        throw new \BadMethodCallException('Not yet implemented');
    }

    public function readAllEventsBackwardAsync(
        Position $position,
        int $maxCount,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): AllEventsSliceTask {
        throw new \BadMethodCallException('Not yet implemented');
    }

    public function setStreamMetadataAsync(
        string $stream,
        int $expectedMetastreamVersion,
        StreamMetadata $metadata,
        UserCredentials $userCredentials = null
    ): WriteResultTask {
        if (empty($stream)) {
            throw new \InvalidArgumentException('Stream cannot be empty');
        }

        if (SystemStreams::isMetastream($stream)) {
            throw new \InvalidArgumentException(sprintf(
                'Setting metadata for metastream \'%s\' is not supported.',
                $stream
            ));
        }

        $metaevent = new EventData(
            EventId::generate(),
            SystemEventTypes::StreamMetadata,
            true,
            json_encode($metadata->toArray()),
            ''
        );

        $operation = new AppendToStreamOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            SystemStreams::metastreamOf($stream),
            $expectedMetastreamVersion,
            [$metaevent],
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getStreamMetadataAsync(string $stream, UserCredentials $userCredentials = null): StreamMetadataResultTask
    {
        $task = $this->readEventAsync(
            SystemStreams::metastreamOf($stream),
            -1,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        $callback = function (EventReadResult $result) use ($stream): StreamMetadataResult {
            switch ($result->status()->value()) {
                case EventReadStatus::Success:
                    $event = $result->event();

                    if (null === $event) {
                        throw new \UnexpectedValueException('Event is null while operation result is Success');
                    }

                    return new StreamMetadataResult(
                        $stream,
                        false,
                        $event->eventNumber(),
                        $event->data()
                    );
                case EventReadStatus::NotFound:
                case EventReadStatus::NoStream:
                    return new StreamMetadataResult($stream, false, -1, '');
                case EventReadStatus::StreamDeleted:
                    return new StreamMetadataResult($stream, true, PHP_INT_MAX, '');
                default:
                    throw new \OutOfRangeException('Unexpected ReadEventResult: ' . $result->status()->value());
            }
        };

        return $task->continueWith($callback, StreamMetadataResultTask::class);
    }

    public function setSystemSettingsAsync(SystemSettings $settings, UserCredentials $userCredentials = null): WriteResultTask
    {
        return $this->appendToStreamAsync(
            SystemStreams::SettingsStream,
            ExpectedVersion::Any,
            [
                new EventData(
                    EventId::generate(),
                    SystemEventTypes::Settings,
                    true,
                    json_encode($settings->toArray()),
                    ''
                ),
            ],
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function createPersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): CreatePersistentSubscriptionTask {
        $operation = new CreatePersistentSubscriptionOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $stream,
            $groupName,
            $settings,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function updatePersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): UpdatePersistentSubscriptionTask {
        $operation = new UpdatePersistentSubscriptionOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $stream,
            $groupName,
            $settings,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function deletePersistentSubscription(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): DeletePersistentSubscriptionTask {
        $operation = new DeletePersistentSubscriptionOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $stream,
            $groupName,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function connectToPersistentSubscription(
        string $stream,
        string $groupName,
        callable $eventAppeared,
        callable $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        UserCredentials $userCredentials = null
    ): EventStorePersistentSubscription {
        // TODO: Implement connectToPersistentSubscription() method.
    }

    public function ack(string $stream, string $groupName, EventId $eventId): Task
    {
        // TODO: Implement ack() method.
    }

    public function ackMultiple(string $stream, string $groupName, iterable $eventIds): Task
    {
        // TODO: Implement ackMultiple() method.
    }

    public function nack(string $stream, string $groupName, EventId $eventId): Task
    {
        // TODO: Implement nack() method.
    }

    public function nackMultiple(string $stream, string $groupName, iterable $eventIds): Task
    {
        // TODO: Implement nackMultiple() method.
    }

    public function replayParked(string $stream, string $groupName): Task
    {
        // TODO: Implement replayParked() method.
    }

    public function getInformationForAllSubscriptions(UserCredentials $userCredentials = null): Task
    {
        $operation = new GetInformationForAllSubscriptionsOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getInformationForSubscriptionsWithStream(string $stream): Task
    {
        // TODO: Implement getInformationAboutSubscriptionsForStream() method.
    }

    public function getInformationForSubscription(string $stream, string $groupName): Task
    {
        // TODO: Implement getInformationAboutSubscription() method.
    }
}
