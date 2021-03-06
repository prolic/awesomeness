<?php

// this file is auto-generated by prolic/fpp
// don't edit this file manually

declare(strict_types=1);

namespace Prooph\EventStore\Data;

final class ReadEventResult
{
    public const OPTIONS = [
        'Success' => 0,
        'NotFound' => 1,
        'NoStream' => 2,
        'StreamDeleted' => 3,
        'Error' => 4,
        'AccessDenied' => 5,
    ];

    public const Success = 0;
    public const NotFound = 1;
    public const NoStream = 2;
    public const StreamDeleted = 3;
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

    public static function notFound(): self
    {
        return new self('NotFound');
    }

    public static function noStream(): self
    {
        return new self('NoStream');
    }

    public static function streamDeleted(): self
    {
        return new self('StreamDeleted');
    }

    public static function error(): self
    {
        return new self('Error');
    }

    public static function accessDenied(): self
    {
        return new self('AccessDenied');
    }

    public static function fromName(string $value): self
    {
        if (! isset(self::OPTIONS[$value])) {
            throw new \InvalidArgumentException('Unknown enum name given');
        }

        return self::{$value}();
    }

    public static function fromValue($value): self
    {
        foreach (self::OPTIONS as $name => $v) {
            if ($v === $value) {
                return self::{$name}();
            }
        }

        throw new \InvalidArgumentException('Unknown enum value given');
    }

    public function equals(ReadEventResult $other): bool
    {
        return \get_class($this) === \get_class($other) && $this->name === $other->name;
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
