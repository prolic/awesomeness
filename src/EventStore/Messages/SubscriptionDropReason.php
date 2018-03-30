<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class SubscriptionDropReason
{
    public const OPTIONS = [
        'Unsubscribed' => 0,
        'AccessDenied' => 1,
        'NotFound' => 2,
        'PersistentSubscriptionDeleted' => 3,
        'SubscriberMaxCountReached' => 4,
    ];

    public const Unsubscribed = 0;
    public const AccessDenied = 1;
    public const NotFound = 2;
    public const PersistentSubscriptionDeleted = 3;
    public const SubscriberMaxCountReached = 4;

    private $name;
    private $value;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->value = self::OPTIONS[$name];
    }

    public static function unsubscribed(): self
    {
        return new self('Unsubscribed');
    }

    public static function accessDenied(): self
    {
        return new self('AccessDenied');
    }

    public static function notFound(): self
    {
        return new self('NotFound');
    }

    public static function persistentSubscriptionDeleted(): self
    {
        return new self('PersistentSubscriptionDeleted');
    }

    public static function subscriberMaxCountReached(): self
    {
        return new self('SubscriberMaxCountReached');
    }

    public static function byName(string $value): self
    {
        if (! isset(self::OPTIONS[$value])) {
            throw new \InvalidArgumentException('Unknown enum name given');
        }

        return self::{$value}();
    }

    public static function byValue($value): self
    {
        foreach (self::OPTIONS as $name => $v) {
            if ($v === $value) {
                return self::{$name}();
            }
        }

        throw new \InvalidArgumentException('Unknown enum value given');
    }

    public function equals(SubscriptionDropReason $other): bool
    {
        return get_class($this) === get_class($other) && $this->value === $other->value;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value()
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
