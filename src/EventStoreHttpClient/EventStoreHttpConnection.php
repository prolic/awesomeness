<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Http\Promise\FulfilledPromise;
use Prooph\EventStoreClient\Common\SystemEventTypes;
use Prooph\EventStoreClient\Common\SystemStreams;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\EventId;
use Prooph\EventStoreClient\EventReadResult;
use Prooph\EventStoreClient\EventReadStatus;
use Prooph\EventStoreClient\EventStorePersistentSubscription;
use Prooph\EventStoreClient\EventStoreSubscriptionConnection;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\Consts;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Prooph\EventStoreClient\Position;
use Prooph\EventStoreClient\StreamMetadata;
use Prooph\EventStoreClient\StreamMetadataResult;
use Prooph\EventStoreClient\SystemSettings;
use Prooph\EventStoreClient\Task;
use Prooph\EventStoreClient\Task\AllEventsSliceTask;
use Prooph\EventStoreClient\Task\CreatePersistentSubscriptionTask;
use Prooph\EventStoreClient\Task\DeletePersistentSubscriptionTask;
use Prooph\EventStoreClient\Task\DeleteResultTask;
use Prooph\EventStoreClient\Task\EventReadResultTask;
use Prooph\EventStoreClient\Task\GetInformationForSubscriptionsTask;
use Prooph\EventStoreClient\Task\GetInformationForSubscriptionTask;
use Prooph\EventStoreClient\Task\ReplayParkedTask;
use Prooph\EventStoreClient\Task\StreamEventsSliceTask;
use Prooph\EventStoreClient\Task\StreamMetadataResultTask;
use Prooph\EventStoreClient\Task\UpdatePersistentSubscriptionTask;
use Prooph\EventStoreClient\Task\WriteResultTask;
use Prooph\EventStoreClient\UserCredentials;
use Prooph\EventStoreHttpClient\ClientOperations\AppendToStreamOperation;
use Prooph\EventStoreHttpClient\ClientOperations\CreatePersistentSubscriptionOperation;
use Prooph\EventStoreHttpClient\ClientOperations\DeletePersistentSubscriptionOperation;
use Prooph\EventStoreHttpClient\ClientOperations\DeleteStreamOperation;
use Prooph\EventStoreHttpClient\ClientOperations\GetInformationForAllSubscriptionsOperation;
use Prooph\EventStoreHttpClient\ClientOperations\GetInformationForSubscriptionOperation;
use Prooph\EventStoreHttpClient\ClientOperations\GetInformationForSubscriptionsWithStreamOperation;
use Prooph\EventStoreHttpClient\ClientOperations\PersistentSubscriptionOperations;
use Prooph\EventStoreHttpClient\ClientOperations\ReadEventOperation;
use Prooph\EventStoreHttpClient\ClientOperations\ReadStreamEventsBackwardOperation;
use Prooph\EventStoreHttpClient\ClientOperations\ReadStreamEventsForwardOperation;
use Prooph\EventStoreHttpClient\ClientOperations\ReplayParkedOperation;
use Prooph\EventStoreHttpClient\ClientOperations\UpdatePersistentSubscriptionOperation;

class EventStoreHttpConnection implements EventStoreSubscriptionConnection
{
    /** @var HttpAsyncClient */
    private $asyncClient;
    /** @var RequestFactory */
    private $requestFactory;
    /** @var UriFactory */
    private $uriFactory;
    /** @var ConnectionSettings */
    private $settings;
    /** @var string */
    private $baseUri;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        ConnectionSettings $settings = null
    ) {
        $this->asyncClient = $asyncClient;
        $this->requestFactory = $requestFactory;
        $this->uriFactory = $uriFactory;
        $this->settings = $settings ?? ConnectionSettings::default();
        $this->baseUri = sprintf(
            '%s://%s:%s',
            $this->settings->useSslConnection() ? 'https' : 'http',
            $this->settings->endPoint()->host(),
            $this->settings->endPoint()->port()
        );
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
        array $events,
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

    /**
     * @param string $stream
     * @param string $groupName
     * @param callable(EventStorePersistentSubscription $subscription, RecordedEvent $event, int $retryCount, Task $task) $eventAppeared
     * @param callable(EventStorePersistentSubscription $subscription, SubscriptionDropReason $reason, Throwable $error)|null $subscriptionDropped
     * @param int $bufferSize
     * @param bool $autoAck
     * @param bool $autoNack
     * @param UserCredentials|null $userCredentials
     * @return EventStorePersistentSubscription
     */
    public function connectToPersistentSubscription(
        string $stream,
        string $groupName,
        callable $eventAppeared,
        callable $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        UserCredentials $userCredentials = null
    ): EventStorePersistentSubscription {
        return new EventStorePersistentSubscription(
            new PersistentSubscriptionOperations(
                $this->asyncClient,
                $this->requestFactory,
                $this->uriFactory,
                $this->baseUri,
                $stream,
                $groupName,
                $userCredentials ?? $this->settings->defaultUserCredentials()
            ),
            $groupName,
            $stream,
            $eventAppeared,
            $subscriptionDropped,
            $bufferSize,
            $autoAck
        );
    }

    public function replayParked(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): ReplayParkedTask {
        $operation = new ReplayParkedOperation(
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

    public function getInformationForAllSubscriptionsAsync(
        UserCredentials $userCredentials = null
    ): GetInformationForSubscriptionsTask {
        $operation = new GetInformationForAllSubscriptionsOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getInformationForSubscriptionsWithStreamAsync(
        string $stream,
        UserCredentials $userCredentials = null
    ): GetInformationForSubscriptionsTask {
        $operation = new GetInformationForSubscriptionsWithStreamOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $stream,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getInformationForSubscriptionAsync(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): GetInformationForSubscriptionTask {
        $operation = new GetInformationForSubscriptionOperation(
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
}
