<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ProjectionManagement;

use Prooph\EventStore\Exception\RuntimeException;

/** @internal */
class ProjectionNotFound extends RuntimeException
{
}
