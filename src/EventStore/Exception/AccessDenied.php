<?php

declare(strict_types=1);

namespace Prooph\EventStore\Exception;

class AccessDenied extends RuntimeException
{
    public static function with(string $stream): StreamDeleted
    {
        return new self(sprintf(
            'Access to stream \'%s\' is denied',
            $stream
        ));
    }
}
