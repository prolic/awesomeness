<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

interface Message
{
    public function __toString(): string;
}
