<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\WriteResult;
use Ramsey\Uuid\Uuid;

/** @internal */
class AppendToStreamOperation
{
    /**
     * @param PDO $connection
     * @param string $stream
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
        int $expectedVersion,
        array $events,
        ?UserCredentials $userCredentials
    ): WriteResult {
        (new AcquireStreamLockOperation())($connection, $stream);

        $statement = $connection->prepare(<<<SQL
SELECT * FROM streams WHERE streamName = ?
SQL
        );
        $statement->execute([$stream]);
        $statement->setFetchMode(PDO::FETCH_OBJ);

        $streamData = $statement->fetch();

        if (! $streamData && $expectedVersion === ExpectedVersion::StreamExists) {
            $this->throw(WrongExpectedVersion::withExpectedVersion($stream, $expectedVersion), $connection, $stream);
        }

        if ($streamData && ($streamData->markDeleted || $streamData->deleted)) {
            $this->throw(StreamDeleted::with($stream), $connection, $stream);
        }

        if ($streamData && $expectedVersion === ExpectedVersion::NoStream) {
            $this->throw(WrongExpectedVersion::withExpectedVersion($stream, $expectedVersion), $connection, $stream);
        }

        $statement = $connection->prepare(<<<SQL
SELECT MAX(eventNumber) as currentVersion FROM events WHERE streamId = ?
SQL
        );
        $statement->execute([$streamData->streamId]);
        $statement->setFetchMode(PDO::FETCH_OBJ);

        $currentVersion = $statement->fetch()->currentVersion;

        if (null === $currentVersion && $expectedVersion > -1) {
            $this->throw(WrongExpectedVersion::withExpectedVersion($stream, $expectedVersion), $connection, $stream);
        }

        if ($currentVersion > -1 && $expectedVersion === ExpectedVersion::EmptyStream) {
            $this->throw(WrongExpectedVersion::withCurrentVersion($stream, $currentVersion, $expectedVersion), $connection, $stream);
        }

        if ($expectedVersion > -1 && $expectedVersion !== $currentVersion) {
            $this->throw(WrongExpectedVersion::withCurrentVersion($stream, $currentVersion, $expectedVersion), $connection, $stream);
        }

        if (! $streamData) {
            $streamId = Uuid::uuid4()->toString();
            $statement = $connection->prepare(<<<SQL
INSERT INTO streams (streamId, streamName, markDeleted, deleted) VALUES (?, ?, ?, ?);
SQL
            );
            $statement->execute([$streamId, $stream, false, false]);
        } else {
            $streamId = $streamData->streamId;
        }

        $sql = <<<SQL
INSERT INTO events (eventId, eventNumber, eventType, data, metaData, streamId, isJson, isMetaData, updated) VALUES 
SQL;
        $sql .= str_repeat('(?, ?, ?, ?, ?, ?, ?, ?, ?), ', count($events));
        $sql = substr($sql, 0, -2);

        $now = new \DateTimeImmutable('NOW', new \DateTimeZone('UTC'));
        $now = DateTimeUtil::format($now);

        $params = [];

        foreach ($events as $event) {
            $params[] = $event->eventId()->toString();
            $params[] = ++$currentVersion;
            $params[] = $event->eventType();
            $params[] = $event->data();
            $params[] = $event->metaData();
            $params[] = $streamId;
            $params[] = $event->isJson();
            $params[] = strlen($event->metaData()) > 0 ? true : false;
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
