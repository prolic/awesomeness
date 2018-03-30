<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class NotHandledReason
{
    public const OPTIONS = [
        'NotReady' => 0,
        'TooBusy' => 1,
        'NotMaster' => 2,
    ];

    public const NotReady = 0;
    public const TooBusy = 1;
    public const NotMaster = 2;

    private $name;
    private $value;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->value = self::OPTIONS[$name];
    }

    public static function notReady(): self
    {
        return new self('NotReady');
    }

    public static function tooBusy(): self
    {
        return new self('TooBusy');
    }

    public static function notMaster(): self
    {
        return new self('NotMaster');
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

    public function equals(NotHandledReason $other): bool
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
