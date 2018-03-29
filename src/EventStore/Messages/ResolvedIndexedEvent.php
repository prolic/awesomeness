<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

class ResolvedIndexedEvent
{
    private $event;
    private $link;

    public function __construct(EventRecord $event, EventRecord $link)
    {
        $this->event = $event;
        $this->link = $link;
    }

    public function event(): EventRecord
    {
        return $this->event;
    }

    public function link(): EventRecord
    {
        return $this->link;
    }
}
