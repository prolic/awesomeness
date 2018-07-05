<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use DateTimeImmutable;
use DateTimeZone;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Messages\EventRecord;
use Prooph\EventStoreClient\Internal\Data\EventRecord as EventRecordData;

class EventRecordConverter
{
    public static function convert(EventRecordData $eventData): EventRecord
    {
        $epoch = $eventData->getCreatedEpoch();
        $date = substr($epoch, 0, -3);
        $micro = substr($epoch, -3);

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
