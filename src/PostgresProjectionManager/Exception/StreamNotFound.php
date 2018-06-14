<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Exception;

/** @internal */
class StreamNotFound extends \RuntimeException
{
    public static function with(string $name): StreamNotFound
    {
        return new self(\sprintf(
            'Stream with name "%s" not found',
            $name
        ));
    }
}
