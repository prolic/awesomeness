<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Exception;

/** @internal */
class ProjectionNotFound extends \RuntimeException implements Exception
{
    public static function with(string $name): ProjectionNotFound
    {
        return new self(\sprintf(
            'Projection with name "%s" not found',
            $name
        ));
    }
}
