<?php

declare(strict_types=1);

namespace Prooph\EventStore\Exception;

class ConnectionClosedException extends RuntimeException
{
    public static function withName(string $name): ConnectionClosedException
    {
        return new self(\sprintf(
            'Connection \'%s\' was closed',
            $name
        ));
    }
}
