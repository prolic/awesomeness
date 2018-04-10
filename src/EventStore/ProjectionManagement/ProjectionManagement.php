<?php

declare(strict_types=1);

namespace Prooph\EventStore\ProjectionManagement;

use Prooph\EventStore\UserCredentials;

interface ProjectionManagement
{
    public function abort(string $name, UserCredentials $userCredentials = null): void;

    public function createOneTime(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        bool $checkpoints,
        bool $emit,
        bool $trackEmittedStreams,
        UserCredentials $userCredentials = null
    ): void;

    public function createContinuous(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        bool $checkpoints,
        bool $emit,
        bool $trackEmittedStreams,
        UserCredentials $userCredentials = null
    ): void;

    public function createTransient(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        UserCredentials $userCredentials = null
    ): void;

    public function delete(
        string $name,
        bool $deleteStateStream,
        bool $deleteCheckpointStream,
        bool $deleteEmittedStreams,
        UserCredentials $userCredentials = null
    ): void;

    public function disable(string $name, UserCredentials $userCredentials = null): void;

    public function enable(string $name, UserCredentials $userCredentials = null): void;

    public function get(string $name, UserCredentials $userCredentials = null): ProjectionDetails;

    /**
     * @return ProjectionDetails[]
     */
    public function getAll(UserCredentials $userCredentials = null): array;

    /**
     * @return ProjectionDetails[]
     */
    public function getAllOneTime(UserCredentials $userCredentials = null): array;

    /**
     * @return ProjectionDetails[]
     */
    public function getAllContinuous(UserCredentials $userCredentials = null): array;

    /**
     * @return ProjectionDetails[]
     */
    public function getAllNonTransient(UserCredentials $userCredentials = null): array;

    /**
     * @return ProjectionDetails[]
     */
    public function getAllQueries(UserCredentials $userCredentials = null): array;

    public function getConfig(string $name, UserCredentials $userCredentials = null): ProjectionConfig;

    public function getDefinition(string $name, UserCredentials $userCredentials = null): ProjectionDefinition;

    public function getQuery(string $name, UserCredentials $userCredentials = null): string;

    public function getResult(string $name, UserCredentials $userCredentials = null): array;

    public function getPartitionResult(
        string $name,
        string $partition,
        UserCredentials $userCredentials = null
    ): array;

    public function getState(string $name, UserCredentials $userCredentials = null): array;

    public function getPartitionState(string $name, string $partition, UserCredentials $userCredentials = null): array;

    public function reset(string $name, UserCredentials $userCredentials = null): void;

    public function updateConfig(string $name, ProjectionConfig $config, UserCredentials $userCredentials = null): void;

    public function updateDefinition(
        string $name,
        string $type,
        ProjectionDefinition $definition,
        UserCredentials $userCredentials = null
    ): void;

    public function updateQuery(
        string $name,
        string $query,
        bool $emitEnabled,
        UserCredentials $userCredentials = null
    ): void;

    // @todo
    // Read projection events based on a query
    // POST /projections/read-events
}
