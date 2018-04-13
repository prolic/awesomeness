<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Internal\PersistentSubscriptionUpdateResult;
use Prooph\EventStore\Internal\PersistentSubscriptionUpdateStatus;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\UserCredentials;

/** @internal */
class UpdatePersistentSubscriptionOperation
{
    public function __invoke(
        PDO $connection,
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials = null
    ): PersistentSubscriptionUpdateResult {
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

            return new PersistentSubscriptionUpdateResult(
                $correlationId->toString(),
                'Group \'' . $groupName . '\' does not exists.',
                PersistentSubscriptionUpdateStatus::notFound()
            );
        }

        $event = $slice->events()[0];

        $data = json_decode($event->data());

        $found = false;
        foreach ($data['entries'] as $key => $entry) {
            if ($entry['stream'] === $stream && $entry['group'] === $groupName) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            (new ReleaseStreamLockOperation())($connection, '$persistentSubscriptionConfig');

            return new PersistentSubscriptionUpdateResult(
                $correlationId->toString(),
                'Group \'' . $groupName . '\' does not exists.',
                PersistentSubscriptionUpdateStatus::notFound()
            );
        }

        $data['entries'][$key] = array_merge(
            [
                'stream' => $stream,
                'group' => $groupName,
            ],
            $settings->toArray()
        );
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
            return new PersistentSubscriptionUpdateResult(
                $correlationId->toString(),
                $e->getMessage(),
                PersistentSubscriptionUpdateStatus::failure()
            );
        } finally {
            (new ReleaseStreamLockOperation())($connection, '$persistentSubscriptionConfig');
        }

        return new PersistentSubscriptionUpdateResult(
            $correlationId->toString(),
            '',
            PersistentSubscriptionUpdateStatus::success()
        );
    }
}
