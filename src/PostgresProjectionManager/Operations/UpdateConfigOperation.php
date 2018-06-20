<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Operations;

use Amp\Coroutine;
use Amp\Postgres\Pool;
use Amp\Postgres\Statement;
use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\ProjectionException;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\ProjectionManagement\Internal\ProjectionConfig as InternalProjectionConfig;
use Prooph\EventStore\ProjectionManagement\ProjectionConfig;
use Prooph\EventStore\Projections\ProjectionEventTypes;
use Prooph\EventStore\Projections\ProjectionNames;
use Prooph\EventStore\Projections\StandardProjections;
use function Amp\Promise\all;

/** @internal */
class UpdateConfigOperation
{
    /** @var Pool */
    private $pool;
    /** @var GetExpectedVersionOperation */
    private $getExpectedVersionOperation;
    /** @var LockOperation */
    private $lockOperation;
    /** @var string[] */
    private $locks = [];

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
        $this->getExpectedVersionOperation = new GetExpectedVersionOperation($pool);
        $this->lockOperation = new LockOperation($pool);
    }

    public function __invoke(
        string $id,
        string $name,
        ProjectionConfig $newConfig,
        InternalProjectionConfig $currentConfig
    ): Generator {
        if (StandardProjections::isStandardProjection($name)) {
            throw new ProjectionException('Cannot override standard projections');
        }

        yield from $this->lockMulti([
            ProjectionNames::ProjectionsMasterStream,
            ProjectionNames::ProjectionsStreamPrefix . $name,
        ]);

        $getExpectedVersionOperation = $this->getExpectedVersionOperation;

        $data = \array_merge($currentConfig->toArray(), $newConfig->toArray());

        $sql = <<<SQL
INSERT INTO events (event_id, event_number, event_type, data, meta_data, stream_name, is_json, updated) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?), (?, ?, ?, ?, ?, ?, ?, ?);
SQL;

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare($sql);

        $now = DateTimeUtil::format(new DateTimeImmutable('NOW', new DateTimeZone('UTC')));

        $params = [
            // master stream
            EventId::generate()->toString(),
            yield from $getExpectedVersionOperation(ProjectionNames::ProjectionsMasterStream) + 1,
            '$prepared',
            \json_encode([
                'id' => $id,
            ]),
            '',
            ProjectionNames::ProjectionsRegistrationStream,
            true,
            $now,
            // projection stream
            EventId::generate()->toString(),
            yield from $getExpectedVersionOperation(ProjectionNames::ProjectionsStreamPrefix . $name) + 1,
            ProjectionEventTypes::ProjectionUpdated,
            \json_encode($data),
            '',
            ProjectionNames::ProjectionsStreamPrefix . $name,
            true,
            $now,
        ];

        yield $statement->execute($params);

        yield from $this->releaseAll();
    }

    private function lockMulti(array $names): Generator
    {
        $promises = [];

        foreach ($names as $name) {
            $promises[] = new Coroutine($this->lockOperation->acquire($name));
            $this->locks[] = $name;
        }

        yield all($promises);
    }

    private function releaseAll(): Generator
    {
        $promises = [];

        foreach ($this->locks as $lock) {
            $promises = new Coroutine($this->lockOperation->release($lock));
        }

        $this->locks = [];

        yield all($promises);
    }
}
