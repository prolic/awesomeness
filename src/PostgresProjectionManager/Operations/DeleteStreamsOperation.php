<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Operations;

use Amp\Coroutine;
use Amp\Postgres\Pool;
use Amp\Postgres\Statement;
use Amp\Postgres\Transaction;
use Generator;
use function Amp\Promise\all;

/** @internal */
class DeleteStreamsOperation
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
        $this->lockOperation = new LockOperation($pool);
    }

    public function __invoke(
        array $streamsToDelete,
        bool $markDeleted
    ): Generator {
        yield from $this->lockMulti($streamsToDelete);

        /** @var Transaction $transaction */
        $transaction = yield $this->pool->transaction();

        $placeholder = \substr(\str_repeat('?, ', \count($streamsToDelete)), 0, -2) . ';';

        $sql = "DELETE FROM events WHERE stream_name IN ($placeholder)";

        /** @var Statement $statement */
        $statement = yield $transaction->prepare($sql);

        yield $statement->execute($streamsToDelete);

        if ($markDeleted) {
            $sql = "UPDATE streams SET mark_deleted = ? WHERE stream_name IN ($placeholder)";
            /** @var Statement $statement */
            $statement = yield $transaction->prepare($sql);

            \array_unshift($streamsToDelete, true);

            yield $statement->execute($streamsToDelete);
        }

        yield $transaction->commit();

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
