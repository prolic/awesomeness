<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class ReadEventCompleted
{
    /** @var ReadEventResult */
    private $result;
    /** @var ResolvedIndexedEvent */
    private $event;
    /** @var string|null */
    private $error;

    /** @internal */
    public function __construct(ReadEventResult $result, ResolvedIndexedEvent $event, ?string $error)
    {
        $this->result = $result;
        $this->event = $event;
        $this->error = $error;
    }

    public function result(): ReadEventResult
    {
        return $this->result;
    }

    public function event(): ResolvedIndexedEvent
    {
        return $this->event;
    }

    public function error(): ?string
    {
        return $this->error;
    }
}
