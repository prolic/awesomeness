<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Operations;

use Amp\Postgres\Pool;
use Amp\Postgres\ResultSet;
use Amp\Postgres\Statement;
use Generator;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\PdoEventStore\Internal\StreamOperation;
use Prooph\PostgresProjectionManager\Exception\StreamNotFound;

/** @internal */
class LoadProjectionStreamRolesOperation
{
    public function __invoke(Pool $pool, string $projectionStream): Generator
    {
        /** @var Statement $statement */
        $statement = yield $pool->prepare(<<<SQL
SELECT streams.mark_deleted, streams.deleted, STRING_AGG(stream_acl.role, ',') as stream_roles
    FROM streams
    LEFT JOIN stream_acl ON streams.stream_name = stream_acl.stream_name AND stream_acl.operation = ?
    WHERE streams.stream_name = ?
    GROUP BY streams.stream_name, streams.mark_deleted, streams.deleted
    LIMIT 1;
SQL
        );

        /** @var ResultSet $result */
        $result = yield $statement->execute([
            StreamOperation::Read,
            $projectionStream,
        ]);

        if (! yield $result->advance(ResultSet::FETCH_OBJECT)) {
            throw StreamNotFound::with($projectionStream);
        }

        $data = $result->getCurrent();

        if ($data->mark_deleted || $data->deleted) {
            throw StreamDeleted::with($projectionStream);
        }

        $streamRoles = [SystemRoles::All];

        if (\is_string($data->stream_roles)) {
            $streamRoles = \explode(',', $data->stream_roles);
        }

        return $streamRoles;
    }
}
