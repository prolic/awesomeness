<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ProjectionManagement;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\ProjectionManagement\AsyncProjectionManagement;
use Prooph\EventStore\ProjectionManagement\ProjectionConfig;
use Prooph\EventStore\Task;
use Prooph\EventStore\Task\CreateProjectionResultTask;
use Prooph\EventStore\Task\GetArrayTask;
use Prooph\EventStore\Task\GetProjectionConfigTask;
use Prooph\EventStore\Task\GetProjectionDefinitionTask;
use Prooph\EventStore\Task\GetProjectionQueryTask;
use Prooph\EventStore\Task\GetProjectionsTask;
use Prooph\EventStore\Task\GetProjectionTask;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ConnectionSettings;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\AbortOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\CreateOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\CreateTransientOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\DeleteOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\DisableOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\EnableOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\GetArrayOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\GetConfigOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\GetDefinitionOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\GetMultiOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\GetOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\GetQueryOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\ResetOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\UpdateConfigOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\UpdateQueryOperation;

final class HttpProjectionManagement implements AsyncProjectionManagement
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

    public function abortAsync(string $name, UserCredentials $userCredentials = null): Task
    {
        $operation = new AbortOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function createOneTimeAsync(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        bool $checkpoints,
        bool $emit,
        bool $trackEmittedStreams,
        UserCredentials $userCredentials = null
    ): CreateProjectionResultTask {
        $operation = new CreateOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            'onetime',
            $type,
            $query,
            $enabled,
            $checkpoints,
            $emit,
            $trackEmittedStreams,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function createContinuousAsync(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        bool $checkpoints,
        bool $emit,
        bool $trackEmittedStreams,
        UserCredentials $userCredentials = null
    ): CreateProjectionResultTask {
        $operation = new CreateOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            'continuous',
            $type,
            $query,
            $enabled,
            $checkpoints,
            $emit,
            $trackEmittedStreams,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function createTransientAsync(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        UserCredentials $userCredentials = null
    ): CreateProjectionResultTask {
        $operation = new CreateTransientOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $type,
            $query,
            $enabled,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function deleteAsync(
        string $name,
        bool $deleteStateStream,
        bool $deleteCheckpointStream,
        bool $deleteEmittedStreams,
        UserCredentials $userCredentials = null
    ): Task {
        $operation = new DeleteOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $deleteStateStream,
            $deleteCheckpointStream,
            $deleteEmittedStreams,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function disableAsync(string $name, UserCredentials $userCredentials = null): Task
    {
        $operation = new DisableOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function enableAsync(string $name, UserCredentials $userCredentials = null): Task
    {
        $operation = new EnableOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getAsync(string $name, UserCredentials $userCredentials = null): GetProjectionTask
    {
        $operation = new GetOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getAllAsync(UserCredentials $userCredentials = null): GetProjectionsTask
    {
        $operation = new GetMultiOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            'any',
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getAllOneTimeAsync(UserCredentials $userCredentials = null): GetProjectionsTask
    {
        $operation = new GetMultiOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            'onetime',
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getAllContinuousAsync(UserCredentials $userCredentials = null): GetProjectionsTask
    {
        $operation = new GetMultiOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            'continuous',
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getAllNonTransientAsync(UserCredentials $userCredentials = null): GetProjectionsTask
    {
        $operation = new GetMultiOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            'all-non-transient',
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getConfigAsync(string $name, UserCredentials $userCredentials = null): GetProjectionConfigTask
    {
        $operation = new GetConfigOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getDefinitionAsync(string $name, UserCredentials $userCredentials = null): GetProjectionDefinitionTask
    {
        $operation = new GetDefinitionOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getQueryAsync(string $name, UserCredentials $userCredentials = null): GetProjectionQueryTask
    {
        $operation = new GetQueryOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getResultAsync(string $name, UserCredentials $userCredentials = null): GetArrayTask
    {
        $operation = new GetArrayOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            'result',
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getPartitionResultAsync(
        string $name,
        string $partition,
        UserCredentials $userCredentials = null
    ): GetArrayTask {
        $operation = new GetArrayOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            'result?parition=' . urlencode($partition),
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getStateAsync(string $name, UserCredentials $userCredentials = null): GetArrayTask
    {
        $operation = new GetArrayOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            'state',
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function getPartitionStateAsync(string $name, string $partition, UserCredentials $userCredentials = null): GetArrayTask
    {
        $operation = new GetArrayOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            'state?parition=' . urlencode($partition),
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function resetAsync(string $name, UserCredentials $userCredentials = null): Task
    {
        $operation = new ResetOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function updateConfigAsync(string $name, ProjectionConfig $config, UserCredentials $userCredentials = null): Task
    {
        $operation = new UpdateConfigOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $config,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }

    public function updateQueryAsync(
        string $name,
        string $type,
        string $query,
        bool $emitEnabled,
        UserCredentials $userCredentials = null
    ): Task {
        $operation = new UpdateQueryOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $type,
            $query,
            $emitEnabled,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation->task();
    }
}
