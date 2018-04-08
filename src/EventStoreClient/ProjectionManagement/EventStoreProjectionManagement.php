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

interface EventStoreProjectionManagement
{
    public function abort(string $name, bool $enableRunAs, UserCredentials $userCredentials = null): Task;

    public function createOneTime(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        bool $checkpoints,
        bool $emit,
        bool $trackEmittedStreams,
        UserCredentials $userCredentials = null
    ): Task;

    public function createContinuous(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        bool $checkpoints,
        bool $emit,
        bool $trackEmittedStreams,
        UserCredentials $userCredentials = null
    ): Task;

    public function createTransient(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        bool $checkpoints,
        bool $emit,
        bool $trackEmittedStreams,
        UserCredentials $userCredentials = null
    ): Task;

    public function delete(
        string $name,
        bool $deleteStateStream,
        bool $deleteCheckpointStream,
        bool $deleteEmittedStreams,
        UserCredentials $userCredentials = null
    ): Task;

    public function disable(string $name, bool $enableRunAs, UserCredentials $userCredentials = null): Task;

    public function enable(string $name, bool $enableRunAs, UserCredentials $userCredentials = null): Task;

    public function get(string $name, UserCredentials $userCredentials = null): GetProjectionTask;

    public function getAll(UserCredentials $userCredentials = null): GetProjectionsTask;

    public function getAllOneTime(UserCredentials $userCredentials = null): GetProjectionsTask;

    public function getAllContinuous(UserCredentials $userCredentials = null): GetProjectionsTask;

    public function getAllNonTransient(UserCredentials $userCredentials = null): GetProjectionsTask;

    public function getAllQueries(UserCredentials $userCredentials = null): GetProjectionsTask;

    public function getConfig(string $name, UserCredentials $userCredentials = null): GetProjectionConfigTask;

    public function getDefinition(string $name, UserCredentials $userCredentials = null): GetProjectionDefinitionTask;

    public function getQuery(string $name, UserCredentials $userCredentials = null): GetProjectionQueryTask;

    public function getResult(string $name, UserCredentials $userCredentials = null): GetArrayTask;

    public function getPartitionResult(
        string $name,
        string $partition,
        UserCredentials $userCredentials = null
    ): GetArrayTask;

    public function getState(string $name, UserCredentials $userCredentials = null): GetArrayTask;

    public function getPartitionState(string $name, UserCredentials $userCredentials = null): GetArrayTask;

    public function reset(string $name, bool $enableRunAs, UserCredentials $userCredentials = null): Task;

    public function updateConfig(string $name, ProjectionConfig $config, UserCredentials $userCredentials = null): Task;

    public function updateDefinition(
        string $name,
        string $type,
        ProjectionDefinition $definition,
        UserCredentials $userCredentials = null
    ): Task;

    public function updateQuery(
        string $name,
        string $query,
        bool $emitEnabled,
        UserCredentials $userCredentials = null
    ): Task;

    // @todo
    // Read projection events based on a query
    // POST /projections/read-events
}
