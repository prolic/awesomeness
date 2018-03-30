<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class ReadStreamResult
{
    public const OPTIONS = [
        'Success' => 0,
        'NoStream' => 1,
        'StreamDeleted' => 2,
        'NotModified' => 3,
        'Error' => 4,
        'AccessDenied' => 5,
    ];

    public const Success = 0;
    public const NoStream = 1;
    public const StreamDeleted = 2;
    public const NotModified = 3;
    public const Error = 4;
    public const AccessDenied = 5;

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

    public static function noStream(): self
    {
        return new self('NoStream');
    }

    public static function streamDeleted(): self
    {
        return new self('StreamDeleted');
    }

    public static function notModified(): self
    {
        return new self('NotModified');
    }

    public static function error(): self
    {
        return new self('Error');
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

    public function equals(ReadStreamResult $other): bool
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
