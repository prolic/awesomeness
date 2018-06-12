<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ProjectionManagement;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\ProjectionManagement\CreateProjectionResult;
use Prooph\EventStore\ProjectionManagement\ProjectionConfig;
use Prooph\EventStore\ProjectionManagement\ProjectionDefinition;
use Prooph\EventStore\ProjectionManagement\ProjectionDetails;
use Prooph\EventStore\ProjectionManagement\ProjectionManagement;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ConnectionSettings;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\AbortOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\CreateOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\CreateTransientOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\DeleteOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\DisableOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\EnableOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\GetArrayOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\GetMultipleProjectionDetailsOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\GetProjectionConfigOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\GetProjectionDefinitionOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\GetProjectionDetailsOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\GetQueryOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\ResetOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\UpdateConfigOperation;
use Prooph\HttpEventStore\ProjectionManagement\ClientOperations\UpdateQueryOperation;

final class HttpProjectionManagement implements ProjectionManagement
{
    /** @var HttpClient */
    private $httpClient;
    /** @var RequestFactory */
    private $requestFactory;
    /** @var UriFactory */
    private $uriFactory;
    /** @var ConnectionSettings */
    private $settings;
    /** @var string */
    private $baseUri;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        ConnectionSettings $settings = null
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->uriFactory = $uriFactory;
        $this->settings = $settings ?? ConnectionSettings::default();
        $this->baseUri = \sprintf(
            '%s://%s:%s',
            $this->settings->useSslConnection() ? 'https' : 'http',
            $this->settings->endPoint()->host(),
            $this->settings->endPoint()->port()
        );
    }

    public function abort(string $name, UserCredentials $userCredentials = null): void
    {
        (new AbortOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
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
    ): CreateProjectionResult {
        return (new CreateOperation())(
            $this->httpClient,
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
    ): CreateProjectionResult {
        return (new CreateOperation())(
            $this->httpClient,
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
    }

    public function createTransient(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        UserCredentials $userCredentials = null
    ): CreateProjectionResult {
        return (new CreateTransientOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $type,
            $query,
            $enabled,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function delete(
        string $name,
        bool $deleteStateStream,
        bool $deleteCheckpointStream,
        bool $deleteEmittedStreams,
        UserCredentials $userCredentials = null
    ): void {
        (new DeleteOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $deleteStateStream,
            $deleteCheckpointStream,
            $deleteEmittedStreams,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function disable(string $name, UserCredentials $userCredentials = null): void
    {
        (new DisableOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function enable(string $name, UserCredentials $userCredentials = null): void
    {
        (new EnableOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function get(string $name, UserCredentials $userCredentials = null): ProjectionDetails
    {
        return (new GetProjectionDetailsOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    /**
     * @return ProjectionDetails[]
     */
    public function getAll(UserCredentials $userCredentials = null): array
    {
        return (new GetMultipleProjectionDetailsOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            'any',
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    /**
     * @return ProjectionDetails[]
     */
    public function getAllOneTime(UserCredentials $userCredentials = null): array
    {
        return (new GetMultipleProjectionDetailsOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            'onetime',
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    /**
     * @return ProjectionDetails[]
     */
    public function getAllContinuous(UserCredentials $userCredentials = null): array
    {
        return (new GetMultipleProjectionDetailsOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            'continuous',
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    /**
     * @return ProjectionDetails[]
     */
    public function getAllNonTransient(UserCredentials $userCredentials = null): array
    {
        return (new GetMultipleProjectionDetailsOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            'all-non-transient',
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function getAllQueries(UserCredentials $userCredentials = null): array
    {
        return (new GetMultipleProjectionDetailsOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            'transient',
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function getConfig(string $name, UserCredentials $userCredentials = null): ProjectionConfig
    {
        return (new GetProjectionConfigOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function getDefinition(string $name, UserCredentials $userCredentials = null): ProjectionDefinition
    {
        return (new GetProjectionDefinitionOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function getQuery(string $name, UserCredentials $userCredentials = null): string
    {
        return (new GetQueryOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function getResult(string $name, UserCredentials $userCredentials = null): array
    {
        return (new GetArrayOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            'result',
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function getPartitionResult(
        string $name,
        string $partition,
        UserCredentials $userCredentials = null
    ): array {
        return (new GetArrayOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            'result?parition=' . \urlencode($partition),
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function getState(string $name, UserCredentials $userCredentials = null): array
    {
        return (new GetArrayOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            'state',
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function getPartitionState(string $name, string $partition, UserCredentials $userCredentials = null): array
    {
        return (new GetArrayOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            'state?parition=' . \urlencode($partition),
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function reset(string $name, UserCredentials $userCredentials = null): void
    {
        (new ResetOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function updateConfig(string $name, ProjectionConfig $config, UserCredentials $userCredentials = null): void
    {
        (new UpdateConfigOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $config,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }

    public function updateQuery(
        string $name,
        string $type,
        string $query,
        bool $emitEnabled,
        UserCredentials $userCredentials = null
    ): void {
        (new UpdateQueryOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $name,
            $type,
            $query,
            $emitEnabled,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }
}
