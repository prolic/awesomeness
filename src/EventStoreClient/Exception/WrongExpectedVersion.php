<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class WrongExpectedVersion extends RuntimeException
{
    public static function withExpectedVersion(string $stream, int $expectedVersion): WrongExpectedVersion
    {
        return new self(sprintf(
            'Append failed due to WrongExpectedVersion. Stream: %s, Expected version: %d',
            $stream,
            $expectedVersion
        ));
    }

    public static function withCurrentVersion(string $stream, int $expectedVersion, int $currentVersion): WrongExpectedVersion
    {
        return new self(sprintf(
            'Append failed due to WrongExpectedVersion. Stream: %s, Expected version: %d, Current version: %d',
            $stream,
            $expectedVersion,
            $currentVersion
        ));
    }
}
