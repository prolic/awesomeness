<?php

declare(strict_types=1);

namespace Prooph\EventStore\Common;

class SystemStreams
{
    public const StreamsStream = '$streams';
    public const SettingsStream = '$settings';
    public const StatsStreamPrefix = '$stats';

    public static function metastreamOf(string $streamId): string
    {
        return '$$' . $streamId;
    }

    public static function isMetastream(string $streamId): bool
    {
        return substr($streamId, 0, 2) === '$$';
    }

    public static function originalStreamOf(string $metastreamId): string
    {
        return substr($metastreamId, 2);
    }
}
