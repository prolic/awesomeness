<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class StreamDeleted extends RuntimeException
{
    public static function with(string $stream): StreamDeleted
    {
        return new self(sprintf(
            'Stream \'%s\' is deleted',
            $stream
        ));
    }
}
