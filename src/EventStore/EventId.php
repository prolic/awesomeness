<?php

declare(strict_types=1);

namespace Prooph\EventStore;

class EventId
{
    private $uuid;

    public static function generate(): EventId
    {
        return new self(\Ramsey\Uuid\Uuid::uuid4());
    }

    public static function fromString(string $eventId): EventId
    {
        return new self(\Ramsey\Uuid\Uuid::fromString($eventId));
    }

    private function __construct(\Ramsey\Uuid\UuidInterface $eventId)
    {
        $this->uuid = $eventId;
    }

    public function toString(): string
    {
        return $this->uuid->toString();
    }

    public function toHex(): string
    {
        return $this->uuid->getHex();
    }

    public function toBinary(): string
    {
        return $this->uuid->getBytes();
    }

    public function __toString(): string
    {
        return $this->uuid->toString();
    }

    public function equals(EventId $other): bool
    {
        return $this->uuid->equals($other->uuid);
    }
}
