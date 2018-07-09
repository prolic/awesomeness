<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use DateTimeImmutable;
use DateTimeZone;
use Prooph\EventStore\Data\EventId;
use Prooph\EventStore\Data\EventRecord;
use Prooph\EventStore\Data\Position;
use Prooph\EventStore\Data\ResolvedEvent;
use Prooph\EventStore\Messages\EventRecord as EventRecordMessage;
use Prooph\EventStore\Messages\ResolvedEvent as ResolvedEventMessage;
use Prooph\EventStore\Messages\ResolvedIndexedEvent as ResolvedIndexedEventMessage;

/** @internal */
class EventMessageConverter
{
    public static function convertEventRecordMessageToEventRecord(EventRecordMessage $message): EventRecord
    {
        $epoch = (string) $message->getCreatedEpoch();
        $date = \substr($epoch, 0, -3);
        $micro = \substr($epoch, -3);

        $created = DateTimeImmutable::createFromFormat(
            'U.u',
            $date . '.' . $micro,
            new DateTimeZone('UTC')
        );

        return new EventRecord(
            $message->getEventStreamId(),
            $message->getEventNumber(),
            EventId::fromBinary($message->getEventId()),
            $message->getEventType(),
            $message->getDataContentType() === 1,
            $message->getData(),
            $message->getMetadata(),
            $created
        );
    }

    public static function convertResolvedEventMessageToResolvedEvent(ResolvedEventMessage $message): ResolvedEvent
    {
        $event = $message->getEvent();
        $link = $message->getLink();

        return new ResolvedEvent(
            $event ? self::convertEventRecordMessageToEventRecord($event) : null,
            $link ? self::convertEventRecordMessageToEventRecord($link) : null,
            new Position($message->getCommitPosition(), $message->getPreparePosition())
        );
    }

    public static function convertResolvedIndexedEventMessageToResolvedEvent(ResolvedIndexedEventMessage $message): ResolvedEvent
    {
        $event = $message->getEvent();
        $link = $message->getLink();

        return new ResolvedEvent(
            $event ? self::convertEventRecordMessageToEventRecord($event) : null,
            $link ? self::convertEventRecordMessageToEventRecord($link) : null,
            null
        );
    }
}
