<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class ScavengeResult
{
    public const OPTIONS = [
        'Success' => 0,
        'InProgress' => 1,
        'Failed' => 2,
    ];

    public const Success = 0;
    public const InProgress = 1;
    public const Failed = 2;

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

    public static function inProgress(): self
    {
        return new self('InProgress');
    }

    public static function failed(): self
    {
        return new self('Failed');
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

    public function equals(ScavengeResult $other): bool
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
