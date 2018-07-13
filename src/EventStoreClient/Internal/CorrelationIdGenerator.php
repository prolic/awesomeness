<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Ramsey\Uuid\Uuid;

/** @internal */
class CorrelationIdGenerator
{
    public static function generate(): string
    {
        return \str_replace('-', '', Uuid::uuid4()->toString());
    }
}
