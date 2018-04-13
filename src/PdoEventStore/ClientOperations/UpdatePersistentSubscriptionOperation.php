<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Internal\PersistentSubscriptionUpdateResult;
use Prooph\EventStore\Internal\PersistentSubscriptionUpdateStatus;
use Prooph\EventStore\PersistentSubscriptionSettings;
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
        $correlationId = EventId::generate();

        try {
            (new AppendToStreamOperation())(
                $connection,
                '$projection-' . $groupName,
                ExpectedVersion::StreamExists,
                [
                    new EventData(
                        $correlationId,
                        '$ProjectionUpdated',
                        true,
                        json_encode($settings->toArray()),
                        ''
                    ),
                ],
                $userCredentials
            );
        } catch (WrongExpectedVersion $e) {
            return new PersistentSubscriptionUpdateResult(
                $correlationId->toString(),
                'Group \'' . $groupName . '\' does not exists.',
                PersistentSubscriptionUpdateStatus::alreadyExists()
            );
        } catch (\Exception $e) {
            return new PersistentSubscriptionUpdateResult(
                $correlationId->toString(),
                $e->getMessage(),
                PersistentSubscriptionUpdateStatus::failure()
            );
        }

        return new PersistentSubscriptionUpdateResult(
            $correlationId->toString(),
            '',
            PersistentSubscriptionUpdateStatus::success()
        );
    }
}
