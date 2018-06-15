<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Exception;

/** @internal */
class ProjectionIsRunning extends \RuntimeException
{
    public static function with(string $name): ProjectionIsRunning
    {
        return new self(\sprintf(
            'Projection with name "%s" is already running',
            $name
        ));
    }
}
