<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Http\Promise\FulfilledPromise;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\Position;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\Task;
use Prooph\EventStore\Task\AllEventsSliceTask;
use Prooph\EventStore\Task\DeleteResultTask;
use Prooph\EventStore\Task\EventReadResultTask;
use Prooph\EventStore\Task\StreamEventsSliceTask;
use Prooph\EventStore\Task\StreamMetadataResultTask;
use Prooph\EventStore\Task\WriteResultTask;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreHttpClient\ClientOperations\AppendToStreamOperation;
use Prooph\EventStoreHttpClient\ClientOperations\DeleteStreamOperation;
use Prooph\EventStoreHttpClient\ClientOperations\ReadEventOperation;
use Prooph\EventStoreHttpClient\ClientOperations\ReadStreamEventsForwardOperation;
use Ramsey\Uuid\Uuid;

class EventStoreHttpConnection implements EventStoreConnection
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
        ?UserCredentials $userCredentials,
        iterable $events
    ): WriteResultTask {
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
        ?UserCredentials $userCredentials
    ): EventReadResultTask {
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
        ?UserCredentials $userCredentials
    ): StreamEventsSliceTask {
        $operation = new ReadStreamEventsForwardOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $stream,
            $start,
            $count,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function readStreamEventsBackwardAsync(
        string $stream,
        int $start,
        int $count,
        ?UserCredentials $userCredentials
    ): StreamEventsSliceTask {
        // TODO: Implement readStreamEventsBackwardAsync() method.
    }

    public function readAllEventsForwardAsync(
        Position $position,
        int $maxCount,
        ?UserCredentials $userCredentials
    ): AllEventsSliceTask {
        // TODO: Implement readAllEventsForwardAsync() method.
    }

    public function readAllEventsBackwardAsync(
        Position $position,
        int $maxCount,
        ?UserCredentials $userCredentials
    ): AllEventsSliceTask {
        // TODO: Implement readAllEventsBackwardAsync() method.
    }

    public function setStreamMetadataAsync(
        string $stream,
        int $expectedMetastreamVersion,
        StreamMetadata $metadata,
        ?UserCredentials $userCredentials
    ): WriteResultTask {
        // TODO: Implement setStreamMetadataAsync() method.
    }

    public function getStreamMetadataAsync(string $stream, ?UserCredentials $userCredentials): StreamMetadataResultTask
    {
        // TODO: Implement getStreamMetadataAsync() method.
    }

    public function setSystemSettingsAsync(SystemSettings $settings, ?UserCredentials $userCredentials): Task
    {
        // TODO: Implement setSystemSettingsAsync() method.
    }
}
