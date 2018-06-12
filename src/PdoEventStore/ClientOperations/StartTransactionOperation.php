<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\UserCredentials;
use Prooph\PdoEventStore\Internal\LockData;

/** @internal */
class StartTransactionOperation
{
    public function __invoke(
        PDO $connection,
        string $stream,
        int $expectedVersion,
        ?UserCredentials $userCredentials
    ): LockData {
        (new AcquireStreamLockOperation())($connection, $stream, $userCredentials);

        /* @var StreamEventsSlice $slice */
        $slice = (new ReadStreamEventsBackwardOperation())(
            $connection,
            $stream,
            PHP_INT_MAX,
            1
        );

        switch ($expectedVersion) {
            case ExpectedVersion::NoStream:
                if (! $slice->status()->equals(SliceReadStatus::streamNotFound())) {
                    if (! $slice->isEndOfStream()) {
                        throw WrongExpectedVersion::withCurrentVersion($stream, $expectedVersion, $slice->lastEventNumber());
                    }
                    throw WrongExpectedVersion::withExpectedVersion($stream, $expectedVersion);
                }
                break;
            case ExpectedVersion::StreamExists:
                if (! $slice->status()->equals(SliceReadStatus::success())) {
                    if (! $slice->isEndOfStream()) {
                        throw WrongExpectedVersion::withCurrentVersion($stream, $expectedVersion, $slice->lastEventNumber());
                    }
                    throw WrongExpectedVersion::withExpectedVersion($stream, $expectedVersion);
                }
                break;
            case ExpectedVersion::EmptyStream:
                if (! $slice->status()->equals(SliceReadStatus::success())
                    && ! $slice->isEndOfStream()
                ) {
                    throw WrongExpectedVersion::withCurrentVersion($stream, $expectedVersion, $slice->lastEventNumber());
                }
                break;
            case ExpectedVersion::Any:
                break;
            default:
                if (! $slice->status()->equals(SliceReadStatus::success())
                    && $expectedVersion !== $slice->lastEventNumber()
                ) {
                    throw WrongExpectedVersion::withCurrentVersion($stream, $expectedVersion, $slice->lastEventNumber());
                }
                break;
        }

        $connection->beginTransaction();

        return new LockData($stream, \random_int(0, PHP_INT_MAX), $slice->lastEventNumber(), 1);
    }
}
