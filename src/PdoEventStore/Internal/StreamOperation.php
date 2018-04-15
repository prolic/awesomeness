<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\Internal;

/** @internal  */
class StreamOperation
{
    public const Read = 0;
    public const Write = 1;
    public const Delete = 2;
    public const MetaRead = 3;
    public const MetaWrite = 4;
}
