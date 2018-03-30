<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class CreatePersistentSubscriptionResult
{
    public const OPTIONS = [
        'Success' => 0,
        'AlreadyExists' => 1,
        'Fail' => 2,
        'AccessDenied' => 3,
    ];

    public const Success = 0;
    public const AlreadyExists = 1;
    public const Fail = 2;
    public const AccessDenied = 3;

    private $name;
    private $value;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->value = self::OPTIONS[$name];
    }

    public static function success(): self
    {
        return new self('Success');
    }

    public static function alreadyExists(): self
    {
        return new self('AlreadyExists');
    }

    public static function fail(): self
    {
        return new self('Fail');
    }

    public static function accessDenied(): self
    {
        return new self('AccessDenied');
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

    public function equals(CreatePersistentSubscriptionResult $other): bool
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
