<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use DateTimeImmutable;
use DateTimeZone;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Internal\Messages\EventRecord as EventRecordMessage;
use Prooph\EventStore\Messages\EventRecord;

/** @internal */
class EventRecordConverter
{
    public static function convert(EventRecordMessage $eventData): EventRecord
    {
        $epoch = (string) $eventData->getCreatedEpoch();
        $date = \substr($epoch, 0, -3);
        $micro = \substr($epoch, -3);

        $created = DateTimeImmutable::createFromFormat(
            'U.u',
            $date . '.' . $micro,
            new DateTimeZone('UTC')
        );

        return new EventRecord(
            $eventData->getEventStreamId(),
            $eventData->getEventNumber(),
            EventId::fromBinary($eventData->getEventId()),
            $eventData->getEventType(),
            $eventData->getDataContentType() === 1,
            $eventData->getData(),
            $eventData->getMetadata(),
            $created
        );
    }
}
