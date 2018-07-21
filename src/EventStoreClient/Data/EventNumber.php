<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Data;

class EventNumber
{
    public const DeletedStream = \PHP_INT_MAX;
    public const Invalid = -\PHP_INT_MAX - 1;
}
