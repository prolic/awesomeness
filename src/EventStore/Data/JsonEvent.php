<?php

declare(strict_types=1);

namespace Prooph\EventStore\Data;

/**
 * Represents a json event to be written.
 */
class JsonEvent extends EventData
{
    public function __construct(?EventId $eventId, string $eventType, string $data = '', string $metaData = '')
    {
        parent::__construct($eventId, $eventType, true, $data, $metaData);
    }
}
