<?php

declare(strict_types=1);

namespace Prooph\EventStore\Exception;

class WrongExpectedVersion extends RuntimeException
{
    public static function duringAppend(string $stream, int $expectedVersion, int $currentVersion): WrongExpectedVersion
    {
        return new self(sprintf(
            'Append failed due to WrongExpectedVersion. Stream: %s, Expected version: %d, Current version: %d',
            $stream,
            $expectedVersion,
            $currentVersion
        ));
    }
}
