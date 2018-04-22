<?php

declare(strict_types=1);

namespace Prooph\EventStore\ProjectionManagement;

use Prooph\EventStore\Exception\RuntimeException;

/** @internal */
class ProjectionNotFound extends RuntimeException
{
    public static function withName(string $name): ProjectionNotFound
    {
        return new self('Projection \'' . $name . '\' not found');
    }
}
