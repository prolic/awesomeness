<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ProjectionManagement;

use PDO;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\ProjectionException;
use Prooph\EventStore\Exception\ProjectionNotFound;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\ProjectionManagement\ProjectionConfig;
use Prooph\EventStore\ProjectionManagement\ProjectionManagement;
use Prooph\EventStore\Projections\ProjectionEventTypes;
use Prooph\EventStore\Projections\ProjectionNames;
use Prooph\EventStore\Projections\StandardProjections;
use Prooph\EventStore\UserCredentials;
use Prooph\PdoEventStore\PdoEventStoreConnection;
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

    public function updateQuery(
        string $name,
        string $type,
        string $query,
        bool $emitEnabled,
        UserCredentials $userCredentials = null
    ): void {
        if ($type !== 'PHP') {
            throw new ProjectionException('Only projection type support for now is \'PHP\'');
        }

        if (StandardProjections::isStandardProjection($name)) {
            throw new ProjectionException('Cannot override standard projections');
        }

        $projectionId = $this->fetchProjectionId($name);

        $this->pdoEventStoreConnection->appendToStream(
            ProjectionNames::ProjectionsMasterStream,
            ExpectedVersion::Any,
            [
                new EventData(
                    EventId::generate(),
                    '$prepared',
                    true,
                    \json_encode([
                        'id' => $projectionId,
                    ]),
                    ''
                ),
            ],
            $userCredentials
        );

        $streamName = ProjectionNames::ProjectionsStreamPrefix . $name;

        $data = $this->fetchLastProjectionStreamDataByEventType($streamName, ProjectionEventTypes::ProjectionUpdated);
        $data['query'] = $query;

        $this->pdoEventStoreConnection->appendToStream(
            ProjectionNames::ProjectionsStreamPrefix . $name,
            ExpectedVersion::Any,
            [
                new EventData(
                    EventId::generate(),
                    ProjectionEventTypes::ProjectionUpdated,
                    true,
                    \json_encode($data),
                    ''
                ),
            ],
            $userCredentials
        );
    }

    private function fetchProjectionId(string $name): string
    {
        $statement = $this->connection->prepare('SELECT projection_id FROM projections WHERE projection_name = ?;');
        $statement->execute([$name]);

        if ($statement->rowCount() === 0) {
            throw ProjectionNotFound::withName($name);
        }

        $statement->setFetchMode(PDO::FETCH_OBJ);

        return $statement->fetch()->projection_id;
    }

    private function fetchLastProjectionStreamDataByEventType(string $name, string $type): array
    {
        $sql = <<<SQL
SELECT
    e2.event_id as event_id,
    e1.event_number as event_number,
    COALESCE(e1.event_type, e2.event_type) as event_type,
    COALESCE(e1.data, e2.data) as data,
    COALESCE(e1.meta_data, e2.meta_data) as meta_data,
    COALESCE(e1.is_json, e2.is_json) as is_json,
    COALESCE(e1.updated, e2.updated) as updated
FROM
    events e1
LEFT JOIN events e2
    ON (e1.link_to_stream_name = e2.stream_name AND e1.link_to_event_number = e2.event_number)
LEFT JOIN streams
    ON streams.stream_name = e1.stream_name
WHERE streams.stream_name = ?
AND e1.event_type >= ?
ORDER BY e1.event_number DESC
LIMIT ?
SQL;

        $statement = $this->connection->prepare($sql);
        $statement->execute([$name, $type]);
        $statement->setFetchMode(PDO::FETCH_OBJ);

        return \json_decode($statement->fetch()->data, true);
    }
}
