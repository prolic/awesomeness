<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

final class VNodeState
{
    public const OPTIONS = [
        'Initializing' => 0,
        'Unknown' => 1,
        'PreReplica' => 2,
        'CatchingUp' => 3,
        'Cloned' => 4,
        'Slave' => 5,
        'PreMaster' => 6,
        'Master' => 7,
        'Manager' => 8,
        'ShuttingDown' => 9,
        'Shutdown' => 10,
    ];

    public const Initializing = 0;
    public const Unknown = 1;
    public const PreReplica = 2;
    public const CatchingUp = 3;
    public const Cloned = 4;
    public const Slave = 5;
    public const PreMaster = 6;
    public const Master = 7;
    public const Manager = 8;
    public const ShuttingDown = 9;
    public const Shutdown = 10;

    private $name;
    private $value;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->value = self::OPTIONS[$name];
    }

    public static function initializing(): self
    {
        return new self('Initializing');
    }

    public static function unknown(): self
    {
        return new self('Unknown');
    }

    public static function preReplica(): self
    {
        return new self('PreReplica');
    }

    public static function catchingUp(): self
    {
        return new self('CatchingUp');
    }

    public static function cloned(): self
    {
        return new self('Cloned');
    }

    public static function slave(): self
    {
        return new self('Slave');
    }

    public static function preMaster(): self
    {
        return new self('PreMaster');
    }

    public static function master(): self
    {
        return new self('Master');
    }

    public static function manager(): self
    {
        return new self('Manager');
    }

    public static function shuttingDown(): self
    {
        return new self('ShuttingDown');
    }

    public static function shutdown(): self
    {
        return new self('Shutdown');
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

    public function equals(VNodeState $other): bool
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
