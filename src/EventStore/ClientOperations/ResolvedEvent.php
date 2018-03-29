<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages\ClientOperations;

use Prooph\EventStore\Position;
use Prooph\EventStore\RecordedEvent;

/** @internal */
interface ResolvedEvent
{
    public function originalStreamId(): string;

    public function originalEventNumber(): int;

    public function originalEvent(): RecordedEvent;

    public function originalPosition(): ?Position;
}
