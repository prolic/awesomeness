<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Internal\PersistentSubscriptionCreateResult;
use Prooph\EventStore\Internal\PersistentSubscriptionCreateStatus;
use Prooph\EventStore\PersistentSubscriptionSettings;
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
        $correlationId = EventId::generate();

        try {
            (new AppendToStreamOperation())(
                $connection,
                '$projection-' . $groupName,
                ExpectedVersion::NoStream,
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
            return new PersistentSubscriptionCreateResult(
                $correlationId->toString(),
                'Group \'' . $groupName . '\' already exists.',
                PersistentSubscriptionCreateStatus::alreadyExists()
            );
        } catch (\Exception $e) {
            return new PersistentSubscriptionCreateResult(
                $correlationId->toString(),
                $e->getMessage(),
                PersistentSubscriptionCreateStatus::failure()
            );
        }

        return new PersistentSubscriptionCreateResult(
            $correlationId->toString(),
            '',
            PersistentSubscriptionCreateStatus::success()
        );
    }
}
