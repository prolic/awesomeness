<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\ByteBuffer;

abstract class AbstractBuffer implements ReadableBuffer, WritableBuffer
{
    abstract public function __toString(): string;

    abstract public function length(): int;
}
