<?php

declare(strict_types=1);

namespace Prooph\EventStore\Data;

/**
 * Represents a raw event to be written.
 */
class RawEvent extends EventData
{
    public function __construct(?EventId $eventId, string $eventType, string $data = '', string $metaData = '')
    {
        parent::__construct($eventId, $eventType, false, $data, $metaData);
    }
}
