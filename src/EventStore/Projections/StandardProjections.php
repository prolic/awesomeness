<?php

declare(strict_types=1);

namespace Prooph\EventStore\Projections;

/** @internal */
class StandardProjections
{
    public const StreamsStandardProjection = '$streams';
    public const StreamByCategoryStandardProjection = '$stream_by_category';
    public const EventByCategoryStandardProjection = '$by_category';
    public const EventByTypeStandardProjection = '$by_event_type';

    private const List = [
        '$streams',
        '$stream_by_category',
        '$by_category',
        '$by_event_type',
    ];

    public static function isStandardProjection(string $name): bool
    {
        return \in_array($name, self::List, true);
    }
}
