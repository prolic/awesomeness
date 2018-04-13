<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\Internal\PersistentSubscriptionCreateResult;
use Prooph\EventStore\Internal\PersistentSubscriptionCreateStatus;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\UserCredentials;

/** @internal */
class CreatePersistentSubscriptionOperation
{
    public function __invoke(
        PDO $connection,
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials = null
    ): PersistentSubscriptionCreateResult {
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
            $eventData = new EventData(
                $correlationId,
                'PersistentConfig1',
                true,
                json_encode([
                    'version' => 1,
                    'updated' => $nowString,
                    'updatedBy' => 'admin',
                    'entries' => [
                        array_merge(
                            [
                                'stream' => $stream,
                                'group' => $groupName,
                            ],
                            $settings->toArray()
                        ),
                    ],

                ]),
                ''
            );

            try {
                (new AppendToStreamOperation())(
                    $connection,
                    '$persistentSubscriptionConfig',
                    ExpectedVersion::NoStream,
                    [$eventData],
                    $userCredentials,
                    true
                );
            } catch (\Exception $e) {
                return new PersistentSubscriptionCreateResult(
                    $correlationId->toString(),
                    $e->getMessage(),
                    PersistentSubscriptionCreateStatus::failure()
                );
            } finally {
                (new ReleaseStreamLockOperation())($connection, '$persistentSubscriptionConfig');
            }

            return new PersistentSubscriptionCreateResult(
                $correlationId->toString(),
                '',
                PersistentSubscriptionCreateStatus::success()
            );
        }

        $event = $slice->events()[0];

        $data = json_decode($event->data());

        foreach ($data['entries'] as $entry) {
            if ($entry['stream'] === $stream && $entry['group'] === $groupName) {
                (new ReleaseStreamLockOperation())($connection, '$persistentSubscriptionConfig');

                return new PersistentSubscriptionCreateResult(
                    $correlationId->toString(),
                    'Group \'' . $groupName . '\' already exists.',
                    PersistentSubscriptionCreateStatus::alreadyExists()
                );
            }
        }

        $data['entries'][] = array_merge(
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
                $userCredentials,
                true
            );
        } catch (\Exception $e) {
            return new PersistentSubscriptionCreateResult(
                $correlationId->toString(),
                $e->getMessage(),
                PersistentSubscriptionCreateStatus::failure()
            );
        } finally {
            (new ReleaseStreamLockOperation())($connection, '$persistentSubscriptionConfig');
        }

        return new PersistentSubscriptionCreateResult(
            $correlationId->toString(),
            '',
            PersistentSubscriptionCreateStatus::success()
        );
    }
}
