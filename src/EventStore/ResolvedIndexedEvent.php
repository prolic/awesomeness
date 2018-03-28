<?php

declare(strict_types=1);

namespace Prooph\EventStore;

final class ResolvedIndexedEvent
{
    private $event;
    private $link;

    public function __construct(\Prooph\EventStore\Messages\EventRecord $event, ?\Prooph\EventStore\Messages\EventRecord $link)
    {
        $this->event = $event;
        $this->link = $link;
    }

    public function event(): \Prooph\EventStore\Messages\EventRecord
    {
        return $this->event;
    }

    public function link(): ?\Prooph\EventStore\Messages\EventRecord
    {
        return $this->link;
    }
}
