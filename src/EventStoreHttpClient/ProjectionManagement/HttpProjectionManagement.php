<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ProjectionManagement;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStoreClient\ProjectionManagement\ProjectionConfig;
use Prooph\EventStoreClient\ProjectionManagement\ProjectionDefinition;
use Prooph\EventStoreClient\ProjectionManagement\ProjectionManagement;
use Prooph\EventStoreClient\Task;
use Prooph\EventStoreClient\Task\GetArrayTask;
use Prooph\EventStoreClient\Task\GetProjectionConfigTask;
use Prooph\EventStoreClient\Task\GetProjectionDefinitionTask;
use Prooph\EventStoreClient\Task\GetProjectionQueryTask;
use Prooph\EventStoreClient\Task\GetProjectionsTask;
use Prooph\EventStoreClient\Task\GetProjectionTask;
use Prooph\EventStoreClient\UserCredentials;
use Prooph\EventStoreHttpClient\ConnectionSettings;
use Prooph\EventStoreHttpClient\ProjectionManagement\ClientOperations\AbortOperation;
use Prooph\EventStoreHttpClient\ProjectionManagement\ClientOperations\DisableOperation;
use Prooph\EventStoreHttpClient\ProjectionManagement\ClientOperations\EnableOperation;
use Prooph\EventStoreHttpClient\ProjectionManagement\ClientOperations\ResetOperation;

final class HttpProjectionManagement implements ProjectionManagement
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

    public function abort(string $name, UserCredentials $userCredentials = null): Task
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

    public function createOneTime(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        bool $checkpoints,
        bool $emit,
        bool $trackEmittedStreams,
        UserCredentials $userCredentials = null
    ): Task {
        // TODO: Implement createOneTime() method.
    }

    public function createContinuous(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        bool $checkpoints,
        bool $emit,
        bool $trackEmittedStreams,
        UserCredentials $userCredentials = null
    ): Task {
        // TODO: Implement createContinuous() method.
    }

    public function createTransient(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        bool $checkpoints,
        bool $emit,
        bool $trackEmittedStreams,
        UserCredentials $userCredentials = null
    ): Task {
        // TODO: Implement createTransient() method.
    }

    public function delete(
        string $name,
        bool $deleteStateStream,
        bool $deleteCheckpointStream,
        bool $deleteEmittedStreams,
        UserCredentials $userCredentials = null
    ): Task {
        // TODO: Implement delete() method.
    }

    public function disable(string $name, UserCredentials $userCredentials = null): Task
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

    public function enable(string $name, UserCredentials $userCredentials = null): Task
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

    public function get(string $name, UserCredentials $userCredentials = null): GetProjectionTask
    {
        // TODO: Implement get() method.
    }

    public function getAll(UserCredentials $userCredentials = null): GetProjectionsTask
    {
        // TODO: Implement getAll() method.
    }

    public function getAllOneTime(UserCredentials $userCredentials = null): GetProjectionsTask
    {
        // TODO: Implement getAllOneTime() method.
    }

    public function getAllContinuous(UserCredentials $userCredentials = null): GetProjectionsTask
    {
        // TODO: Implement getAllContinuous() method.
    }

    public function getAllNonTransient(UserCredentials $userCredentials = null): GetProjectionsTask
    {
        // TODO: Implement getAllNonTransient() method.
    }

    public function getAllQueries(UserCredentials $userCredentials = null): GetProjectionsTask
    {
        // TODO: Implement getAllQueries() method.
    }

    public function getConfig(string $name, UserCredentials $userCredentials = null): GetProjectionConfigTask
    {
        // TODO: Implement getConfig() method.
    }

    public function getDefinition(string $name, UserCredentials $userCredentials = null): GetProjectionDefinitionTask
    {
        // TODO: Implement getDefinition() method.
    }

    public function getQuery(string $name, UserCredentials $userCredentials = null): GetProjectionQueryTask
    {
        // TODO: Implement getQuery() method.
    }

    public function getResult(string $name, UserCredentials $userCredentials = null): GetArrayTask
    {
        // TODO: Implement getResult() method.
    }

    public function getPartitionResult(
        string $name,
        string $partition,
        UserCredentials $userCredentials = null
    ): GetArrayTask {
        // TODO: Implement getPartitionResult() method.
    }

    public function getState(string $name, UserCredentials $userCredentials = null): GetArrayTask
    {
        // TODO: Implement getState() method.
    }

    public function getPartitionState(string $name, UserCredentials $userCredentials = null): GetArrayTask
    {
        // TODO: Implement getPartitionState() method.
    }

    public function reset(string $name, UserCredentials $userCredentials = null): Task
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

    public function updateConfig(string $name, ProjectionConfig $config, UserCredentials $userCredentials = null): Task
    {
        // TODO: Implement updateConfig() method.
    }

    public function updateDefinition(
        string $name,
        string $type,
        ProjectionDefinition $definition,
        UserCredentials $userCredentials = null
    ): Task {
        // TODO: Implement updateDefinition() method.
    }

    public function updateQuery(
        string $name,
        string $query,
        bool $emitEnabled,
        UserCredentials $userCredentials = null
    ): Task {
        // TODO: Implement updateQuery() method.
    }
}
