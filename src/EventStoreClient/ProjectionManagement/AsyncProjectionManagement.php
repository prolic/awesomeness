<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\ProjectionManagement;

use Prooph\EventStoreClient\Task;
use Prooph\EventStoreClient\Task\GetArrayTask;
use Prooph\EventStoreClient\Task\GetProjectionConfigTask;
use Prooph\EventStoreClient\Task\GetProjectionDefinitionTask;
use Prooph\EventStoreClient\Task\GetProjectionQueryTask;
use Prooph\EventStoreClient\Task\GetProjectionsTask;
use Prooph\EventStoreClient\Task\GetProjectionTask;
use Prooph\EventStoreClient\UserCredentials;

interface AsyncProjectionManagement
{
    public function abortAsync(string $name, UserCredentials $userCredentials = null): Task;

    public function createOneTimeAsync(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        bool $checkpoints,
        bool $emit,
        bool $trackEmittedStreams,
        UserCredentials $userCredentials = null
    ): Task;

    public function createContinuousAsync(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        bool $checkpoints,
        bool $emit,
        bool $trackEmittedStreams,
        UserCredentials $userCredentials = null
    ): Task;

    public function createTransientAsync(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        bool $checkpoints,
        bool $emit,
        bool $trackEmittedStreams,
        UserCredentials $userCredentials = null
    ): Task;

    public function deleteAsync(
        string $name,
        bool $deleteStateStream,
        bool $deleteCheckpointStream,
        bool $deleteEmittedStreams,
        UserCredentials $userCredentials = null
    ): Task;

    public function disableAsync(string $name, UserCredentials $userCredentials = null): Task;

    public function enableAsync(string $name, UserCredentials $userCredentials = null): Task;

    public function getAsync(string $name, UserCredentials $userCredentials = null): GetProjectionTask;

    public function getAllAsync(UserCredentials $userCredentials = null): GetProjectionsTask;

    public function getAllOneTimeAsync(UserCredentials $userCredentials = null): GetProjectionsTask;

    public function getAllContinuousAsync(UserCredentials $userCredentials = null): GetProjectionsTask;

    public function getAllNonTransientAsync(UserCredentials $userCredentials = null): GetProjectionsTask;

    public function getAllQueriesAsync(UserCredentials $userCredentials = null): GetProjectionsTask;

    public function getConfigAsync(string $name, UserCredentials $userCredentials = null): GetProjectionConfigTask;

    public function getDefinitionAsync(string $name, UserCredentials $userCredentials = null): GetProjectionDefinitionTask;

    public function getQueryAsync(string $name, UserCredentials $userCredentials = null): GetProjectionQueryTask;

    public function getResultAsync(string $name, UserCredentials $userCredentials = null): GetArrayTask;

    public function getPartitionResultAsync(
        string $name,
        string $partition,
        UserCredentials $userCredentials = null
    ): GetArrayTask;

    public function getStateAsync(string $name, UserCredentials $userCredentials = null): GetArrayTask;

    public function getPartitionStateAsync(string $name, UserCredentials $userCredentials = null): GetArrayTask;

    public function resetAsync(string $name, UserCredentials $userCredentials = null): Task;

    public function updateConfigAsync(string $name, ProjectionConfig $config, UserCredentials $userCredentials = null): Task;

    public function updateDefinitionAsync(
        string $name,
        string $type,
        ProjectionDefinition $definition,
        UserCredentials $userCredentials = null
    ): Task;

    public function updateQueryAsync(
        string $name,
        string $query,
        bool $emitEnabled,
        UserCredentials $userCredentials = null
    ): Task;

    // @todo
    // Read projection events based on a query
    // POST /projections/read-events
}
