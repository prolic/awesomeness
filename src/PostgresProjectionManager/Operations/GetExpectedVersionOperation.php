<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Operations;

use Amp\Postgres\Pool;
use Amp\Postgres\ResultSet;
use Amp\Postgres\Statement;
use Generator;
use Prooph\EventStore\Data\ExpectedVersion;

/** @internal */
class GetExpectedVersionOperation
{
    /** @var Pool */
    private $pool;
    /** @var Statement */
    private $statement;

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
    }

    public function __invoke(string $streamName): Generator
    {
        if (null === $this->statement || ! $this->statement->isAlive()) {
            $this->statement = yield $this->pool->prepare(<<<SQL
SELECT MAX(event_number) as current_version FROM events WHERE stream_name = ?
SQL
            );
        }

        $expectedVersion = ExpectedVersion::NoStream;

        /** @var ResultSet $result */
        $result = yield $this->statement->execute([$streamName]);

        while (yield $result->advance(ResultSet::FETCH_OBJECT)) {
            $expectedVersion = $result->getCurrent()->current_version;
        }

        if (null === $expectedVersion) {
            $expectedVersion = ExpectedVersion::EmptyStream;
        }

        return $expectedVersion;
    }
}
