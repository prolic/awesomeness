<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Operations;

use Amp\Coroutine;
use Amp\Postgres\Pool;
use Amp\Postgres\Statement;
use Generator;
use Prooph\EventStore\Data\EventId;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\ProjectionManagement\Internal\ProjectionConfig as InternalProjectionConfig;
use Prooph\EventStore\ProjectionManagement\ProjectionConfig;
use Prooph\EventStore\Projections\ProjectionEventTypes;
use Prooph\EventStore\Projections\ProjectionNamesBuilder;
use function Amp\Promise\all;

/** @internal */
class UpdateProjectionOperation
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
        ? ProjectionConfig $newConfig,
        string $query,
        bool $emitEnabled,
        array $currentConfiguration
    ): Generator {
        yield from $this->lockMulti([
            ProjectionNamesBuilder::ProjectionsMasterStream,
            ProjectionNamesBuilder::ProjectionsStreamPrefix . $name,
        ]);

        $getExpectedVersionOperation = $this->getExpectedVersionOperation;

        if ($newConfig) {
            $data = \array_merge($currentConfiguration, $newConfig->toArray());
        } else {
            $data = $currentConfiguration;
        }
        $data['query'] = $query;
        $data['emitEnabled'] = $emitEnabled;

        $sql = <<<SQL
INSERT INTO events (event_id, event_number, event_type, data, meta_data, stream_name, is_json, updated) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?), (?, ?, ?, ?, ?, ?, ?, ?);
SQL;

        /** @var Statement $statement */
        $statement = yield $this->pool->prepare($sql);

        $now = DateTimeUtil::format(DateTimeUtil::utcNow());

        $params = [
            // master stream
            EventId::generate()->toString(),
            yield from $getExpectedVersionOperation(ProjectionNamesBuilder::ProjectionsMasterStream) + 1,
            '$prepared',
            \json_encode([
                'id' => $id,
            ]),
            '',
            ProjectionNamesBuilder::ProjectionsMasterStream,
            true,
            $now,
            // projection stream
            EventId::generate()->toString(),
            yield from $getExpectedVersionOperation(ProjectionNamesBuilder::ProjectionsStreamPrefix . $name) + 1,
            ProjectionEventTypes::ProjectionUpdated,
            \json_encode($data),
            '',
            ProjectionNamesBuilder::ProjectionsStreamPrefix . $name,
            true,
            $now,
        ];

        yield $statement->execute($params);

        yield from $this->releaseAll();

        return InternalProjectionConfig::fromArray($data);
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
