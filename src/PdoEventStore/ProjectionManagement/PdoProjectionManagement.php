<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ProjectionManagement;

use PDO;
use PDOException;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\ProjectionException;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\ProjectionManagement\CreateProjectionResult;
use Prooph\EventStore\ProjectionManagement\ProjectionConfig;
use Prooph\EventStore\ProjectionManagement\ProjectionDefinition;
use Prooph\EventStore\ProjectionManagement\ProjectionDetails;
use Prooph\EventStore\ProjectionManagement\ProjectionManagement;
use Prooph\EventStore\Projections\ProjectionEventTypes;
use Prooph\EventStore\Projections\ProjectionNames;
use Prooph\EventStore\Projections\StandardProjections;
use Prooph\EventStore\UserCredentials;
use Prooph\PdoEventStore\PdoEventStoreConnection;
use Ramsey\Uuid\Uuid;
use ReflectionMethod;

final class PdoProjectionManagement implements ProjectionManagement
{
    /** @var PdoEventStoreConnection */
    private $pdoEventStoreConnection;
    /** @var ReflectionMethod */
    private $userRolesMethod;

    /** @var PdoEventStoreConnection */
    private $eventStoreConnection;
    /** @var PDO */
    private $connection;

    public function __construct(PdoEventStoreConnection $eventStoreConnection, PDO $connection)
    {
        $this->eventStoreConnection = $eventStoreConnection;
        $this->connection = $connection;

        $this->userRolesMethod = new ReflectionMethod($connection, 'userRoles');
        $this->userRolesMethod->setAccessible(true);
    }

    public function abort(string $name, UserCredentials $userCredentials = null): void
    {
        // TODO: Implement abort() method.
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
        return $this->createProjection(
            'OneTime',
            $name,
            $type,
            $query,
            $enabled,
            $checkpoints,
            $emit,
            $trackEmittedStreams,
            $userCredentials
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
        return $this->createProjection(
            'Continuous',
            $name,
            $type,
            $query,
            $enabled,
            $checkpoints,
            $emit,
            $trackEmittedStreams,
            $userCredentials
        );
    }

    public function createTransient(
        string $name,
        string $type,
        string $query,
        bool $enabled,
        UserCredentials $userCredentials = null
    ): CreateProjectionResult {
        return $this->createProjection(
            'Transient',
            $name,
            $type,
            $query,
            $enabled,
            null,
            null,
            null,
            $userCredentials
        );
    }

    public function delete(
        string $name,
        bool $deleteStateStream,
        bool $deleteCheckpointStream,
        bool $deleteEmittedStreams,
        UserCredentials $userCredentials = null
    ): void {
        // TODO: Implement delete() method.
    }

    public function disable(string $name, UserCredentials $userCredentials = null): void
    {
        // TODO: Implement disable() method.
    }

    public function enable(string $name, UserCredentials $userCredentials = null): void
    {
        // TODO: Implement enable() method.
    }

    public function get(string $name, UserCredentials $userCredentials = null): ProjectionDetails
    {
        // TODO: Implement get() method.
    }

    /**
     * @return ProjectionDetails[]
     */
    public function getAll(UserCredentials $userCredentials = null): array
    {
        // TODO: Implement getAll() method.
    }

    /**
     * @return ProjectionDetails[]
     */
    public function getAllOneTime(UserCredentials $userCredentials = null): array
    {
        // TODO: Implement getAllOneTime() method.
    }

    /**
     * @return ProjectionDetails[]
     */
    public function getAllContinuous(UserCredentials $userCredentials = null): array
    {
        // TODO: Implement getAllContinuous() method.
    }

    /**
     * @return ProjectionDetails[]
     */
    public function getAllNonTransient(UserCredentials $userCredentials = null): array
    {
        // TODO: Implement getAllNonTransient() method.
    }

    /**
     * @return ProjectionDetails[]
     */
    public function getAllQueries(UserCredentials $userCredentials = null): array
    {
        // TODO: Implement getAllQueries() method.
    }

    public function getConfig(string $name, UserCredentials $userCredentials = null): ProjectionConfig
    {
        // TODO: Implement getConfig() method.
    }

    public function getDefinition(string $name, UserCredentials $userCredentials = null): ProjectionDefinition
    {
        // TODO: Implement getDefinition() method.
    }

    public function getQuery(string $name, UserCredentials $userCredentials = null): string
    {
        // TODO: Implement getQuery() method.
    }

    public function getResult(string $name, UserCredentials $userCredentials = null): array
    {
        // TODO: Implement getResult() method.
    }

    public function getPartitionResult(
        string $name,
        string $partition,
        UserCredentials $userCredentials = null
    ): array {
        // TODO: Implement getPartitionResult() method.
    }

    public function getState(string $name, UserCredentials $userCredentials = null): array
    {
        // TODO: Implement getState() method.
    }

    public function getPartitionState(string $name, string $partition, UserCredentials $userCredentials = null): array
    {
        // TODO: Implement getPartitionState() method.
    }

    public function reset(string $name, UserCredentials $userCredentials = null): void
    {
        // TODO: Implement reset() method.
    }

    public function updateConfig(string $name, ProjectionConfig $config, UserCredentials $userCredentials = null): void
    {
        // TODO: Implement updateConfig() method.
    }

    public function updateQuery(
        string $name,
        string $type,
        string $query,
        bool $emitEnabled,
        UserCredentials $userCredentials = null
    ): void {
        // TODO: Implement updateQuery() method.
    }

    private function defaultProjectionConfig(): array
    {
        return [
            'checkpointHandledThreshold' => 4000,
            'checkpointUnhandledBytesThreshold' => 10000000,
            'pendingEventsThreshold' => 5000,
            'maxWriteBatchLength' => 500,
        ];
    }

    private function createProjection(
        string $mode,
        string $name,
        string $type,
        string $query,
        ?bool $enabled,
        ?bool $checkpoints,
        ?bool $emit,
        bool $trackEmittedStreams,
        UserCredentials $userCredentials = null
    ): CreateProjectionResult {
        if ($type !== 'PHP') {
            throw new ProjectionException('Only projection type support for now is \'PHP\'');
        }

        if (StandardProjections::isStandardProjection($name)) {
            throw new ProjectionException('Cannot override standard projections');
        }

        if ($userCredentials) {
            $runAs = $userCredentials->username();
            $cred = $userCredentials;
        } elseif ($this->pdoEventStoreConnection->settings()->defaultUserCredentials()) {
            $cred = $this->pdoEventStoreConnection->settings()->defaultUserCredentials();
            $runAs = $cred->username();
        } else {
            throw AccessDenied::toProjectionManagementOperation();
        }

        $roles = $this->userRolesMethod->invoke($this->pdoEventStoreConnection, $cred);

        $projectionId = str_replace('-', '', Uuid::uuid4()->toString());

        try {
            $statement = $this->connection->prepare('INSERT INTO projections (projection_name, projection_id) VALUES (?, ?);');
            $statement->execute([
                $name,
                $projectionId,
            ]);
        } catch (PDOException $e) {
            return CreateProjectionResult::conflict();
        }

        $this->pdoEventStoreConnection->appendToStream(
            ProjectionNames::ProjectionsRegistrationStream,
            ExpectedVersion::Any,
            [
                new EventData(
                    EventId::generate(),
                    ProjectionEventTypes::ProjectionCreated,
                    false,
                    $name,
                    ''
                ),
            ],
            $userCredentials
        );

        if ($mode === 'Transient') {
            $eventData = [
                'handlerType' => 'PHP',
                'query' => $query,
                'mode' => $mode,
                'enabled' => $enabled,
                'runAs' => [
                    'name' => $runAs,
                    'roles' => $roles,
                ],
            ];
        } else {
            $eventData = [
                'handlerType' => 'PHP',
                'query' => $query,
                'mode' => $mode,
                'enabled' => $enabled,
                'emitEnabled' => $emit,
                'checkpointsDisabled' => ! $checkpoints,
                'trackEmittedStreams' => $trackEmittedStreams,
                'runAs' => [
                    'name' => $runAs,
                    'roles' => $roles,
                ],
            ];
        }

        $this->pdoEventStoreConnection->appendToStream(
            ProjectionNames::ProjectionsMasterStream,
            ExpectedVersion::Any,
            [
                new EventData(
                    EventId::generate(),
                    '$prepared',
                    true,
                    json_encode([
                        'id' => $projectionId,
                    ]),
                    ''
                ),
            ],
            $userCredentials
        );

        $this->pdoEventStoreConnection->appendToStream(
            ProjectionNames::ProjectionsStreamPrefix . $name,
            ExpectedVersion::Any,
            [
                new EventData(
                    EventId::generate(),
                    ProjectionEventTypes::ProjectionUpdated,
                    true,
                    json_encode(array_merge(
                        $eventData,
                        $this->defaultProjectionConfig()
                    )),
                    ''
                ),
            ],
            $userCredentials
        );

        return CreateProjectionResult::success();
    }
}
