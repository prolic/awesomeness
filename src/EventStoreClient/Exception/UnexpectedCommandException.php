<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class UnexpectedCommandException extends \RuntimeException implements Exception
{
    public static function with(string $expectedCommand, string $actualCommand): UnexpectedCommandException
    {
        return new self(\sprintf(
            'Unexpected command \'%s\': expected \'%s\'',
            $actualCommand,
            $expectedCommand
        ));
    }
}
