<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Internal\PersistentSubscriptionDeleteResult;
use Prooph\EventStore\Internal\PersistentSubscriptionDeleteStatus;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\UserCredentials;

/** @internal */
class DeletePersistentSubscriptionOperation
{
    public function __invoke(
        PDO $connection,
        string $stream,
        string $groupName,
        ?UserCredentials $userCredentials = null
    ): PersistentSubscriptionDeleteResult {
        (new AcquireStreamLockOperation())(
            $connection,
            '$persistentSubscriptionConfig'
        );

        /* @var StreamEventsSlice $slice */
        $slice = (new ReadStreamEventsBackwardOperation())(
            $connection,
            '$persistentSubscriptionConfig',
            PHP_INT_MAX,
            1
        );

        $correlationId = EventId::generate();
        $now = new \DateTimeImmutable('NOW', new \DateTimeZone('UTC'));
        $nowString = DateTimeUtil::format($now);

        if ($slice->status()->equals(SliceReadStatus::streamDeleted())) {
            (new ReleaseStreamLockOperation())($connection, '$persistentSubscriptionConfig');
            throw new RuntimeException('$persistentSubscriptionConfig stream deleted, this should never happen!');
        } elseif ($slice->status()->equals(SliceReadStatus::streamNotFound())
            || $slice->isEndOfStream()
        ) {
            (new ReleaseStreamLockOperation())($connection, '$persistentSubscriptionConfig');

            return new PersistentSubscriptionDeleteResult(
                $correlationId->toString(),
                'Group \'' . $groupName . '\' does not exists.',
                PersistentSubscriptionDeleteStatus::doesNotExist()
            );
        }

        $event = $slice->events()[0];

        $data = json_decode($event->data());

        $found = false;
        $entries = [];
        foreach ($data['entries'] as $key => $entry) {
            if ($entry['stream'] === $stream && $entry['group'] === $groupName) {
                $found = true;
                continue;
            }

            $entries[] = $entry;
        }

        if (! $found) {
            (new ReleaseStreamLockOperation())($connection, '$persistentSubscriptionConfig');

            return new PersistentSubscriptionDeleteResult(
                $correlationId->toString(),
                'Group \'' . $groupName . '\' does not exists.',
                PersistentSubscriptionDeleteStatus::doesNotExist()
            );
        }

        $data['entries'] = $entries;
        $data['updated'] = $nowString;

        try {
            (new AppendToStreamOperation())(
                $connection,
                '$persistentSubscriptionConfig',
                $slice->lastEventNumber(),
                [
                    new EventData(
                        $correlationId,
                        'PersistentConfig1',
                        true,
                        json_encode($data),
                        ''
                    ),
                ],
                $userCredentials
            );
        } catch (\Exception $e) {
            return new PersistentSubscriptionDeleteResult(
                $correlationId->toString(),
                $e->getMessage(),
                PersistentSubscriptionDeleteStatus::failure()
            );
        } finally {
            (new ReleaseStreamLockOperation())($connection, '$persistentSubscriptionConfig');
        }

        return new PersistentSubscriptionDeleteResult(
            $correlationId->toString(),
            '',
            PersistentSubscriptionDeleteStatus::success()
        );
    }
}
