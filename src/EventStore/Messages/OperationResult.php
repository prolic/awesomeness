<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
final class OperationResult
{
    public const OPTIONS = [
        'Success' => 0,
        'PrepareTimeout' => 1,
        'CommitTimeout' => 2,
        'ForwardTimeout' => 3,
        'WrongExpectedVersion' => 4,
        'StreamDeleted' => 5,
        'InvalidTransaction' => 6,
        'AccessDenied' => 7,
    ];

    public const Success = 0;
    public const PrepareTimeout = 1;
    public const CommitTimeout = 2;
    public const ForwardTimeout = 3;
    public const WrongExpectedVersion = 4;
    public const StreamDeleted = 5;
    public const InvalidTransaction = 6;
    public const AccessDenied = 7;

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

    public static function prepareTimeout(): self
    {
        return new self('PrepareTimeout');
    }

    public static function commitTimeout(): self
    {
        return new self('CommitTimeout');
    }

    public static function forwardTimeout(): self
    {
        return new self('ForwardTimeout');
    }

    public static function wrongExpectedVersion(): self
    {
        return new self('WrongExpectedVersion');
    }

    public static function streamDeleted(): self
    {
        return new self('StreamDeleted');
    }

    public static function invalidTransaction(): self
    {
        return new self('InvalidTransaction');
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

    public function equals(OperationResult $other): bool
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
