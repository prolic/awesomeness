<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\Data\EventData;
use Prooph\EventStore\Data\ExpectedVersion;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Data\WriteResult;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\Internal\DateTimeUtil;

/** @internal */
class AppendToStreamOperation
{
    /**
     * @param PDO $connection
     * @param string $stream
     * @param bool $streamExists
     * @param int $expectedVersion
     * @param EventData[] $events
     * @param UserCredentials|null $userCredentials
     *
     * @return WriteResult
     *
     * @throws \Throwable
     */
    public function __invoke(
        PDO $connection,
        string $stream,
        bool $streamExists,
        int $expectedVersion,
        array $events,
        ?UserCredentials $userCredentials
    ): WriteResult {
        (new AcquireStreamLockOperation())($connection, $stream, $userCredentials);

        if (! $streamExists && $expectedVersion === ExpectedVersion::StreamExists) {
            $this->throw(WrongExpectedVersion::withExpectedVersion($stream, $expectedVersion), $connection, $stream);
        }

        if ($streamExists && $expectedVersion === ExpectedVersion::NoStream) {
            $this->throw(WrongExpectedVersion::withExpectedVersion($stream, $expectedVersion), $connection, $stream);
        }

        if ($streamExists) {
            $statement = $connection->prepare(<<<SQL
SELECT MAX(event_number) as current_version FROM events WHERE stream_name = ?
SQL
            );
            $statement->execute([$stream]);
            $statement->setFetchMode(PDO::FETCH_OBJ);

            $currentVersion = $statement->fetch()->current_version ?? -1;
        } else {
            $currentVersion = -1;
        }

        if (null === $currentVersion && $expectedVersion > -1) {
            $this->throw(WrongExpectedVersion::withExpectedVersion($stream, $expectedVersion), $connection, $stream);
        }

        if ($currentVersion > -1 && $expectedVersion === ExpectedVersion::EmptyStream) {
            $this->throw(WrongExpectedVersion::withCurrentVersion($stream, $currentVersion, $expectedVersion), $connection, $stream);
        }

        if ($expectedVersion > -1 && $expectedVersion !== $currentVersion) {
            $this->throw(WrongExpectedVersion::withCurrentVersion($stream, $currentVersion, $expectedVersion), $connection, $stream);
        }

        if (! $streamExists) {
            $statement = $connection->prepare(<<<SQL
INSERT INTO streams (stream_name, mark_deleted, deleted) VALUES (?, ?, ?);
SQL
            );
            $statement->execute([$stream, 0, 0]);
        }

        $sql = <<<SQL
INSERT INTO events (event_id, event_number, event_type, data, meta_data, stream_name, is_json, updated) VALUES
SQL;
        $sql .= \str_repeat('(?, ?, ?, ?, ?, ?, ?, ?), ', \count($events));
        $sql = \substr($sql, 0, -2);

        $now = DateTimeUtil::format(DateTimeUtil::utcNow());

        $params = [];

        foreach ($events as $event) {
            $params[] = $event->eventId()->toString();
            $params[] = ++$currentVersion;
            $params[] = $event->eventType();
            $params[] = $event->data();
            $params[] = $event->metaData();
            $params[] = $stream;
            $params[] = $event->isJson() ? 1 : 0;
            $params[] = $now;
        }

        $statement = $connection->prepare($sql);
        $statement->execute($params);

        (new ReleaseStreamLockOperation())($connection, $stream);

        return new WriteResult();
    }

    /** @throws \Throwable */
    private function throw(\Throwable $e, PDO $connection, string $stream): void
    {
        (new ReleaseStreamLockOperation())($connection, $stream);

        throw $e;
    }
}
