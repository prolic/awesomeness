<?php

declare(strict_types=1);

namespace Prooph\EventStore\Exception;

class WrongExpectedVersionException extends RuntimeException
{
    public static function duringAppend(string $stream, int $expectedVersion, int $currentVersion): WrongExpectedVersionException
    {
        return new self(sprintf(
            'Append failed due to WrongExpectedVersion. Stream: %s, Expected version: %d, Current version: %d',
            $stream,
            $expectedVersion,
            $currentVersion
        ));
    }
}
