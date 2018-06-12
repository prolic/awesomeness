<?php

declare(strict_types=1);

namespace Prooph\EventStore\Projections;

/** @internal */
class ProjectionMode
{
    public const OPTIONS = [
        'Transient' => 0,
        'OneTime' => 1,
        'Continuous' => 4,
        'AllNonTransient' => 999,
    ];

    public const Transient = 0;
    public const OneTime = 1;
    public const Continuous = 4;
    public const AllNonTransient = 999;

    private $name;
    private $value;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->value = self::OPTIONS[$name];
    }

    public static function transient(): self
    {
        return new self('Transient');
    }

    public static function oneTime(): self
    {
        return new self('OneTime');
    }

    public static function continuous(): self
    {
        return new self('Continuous');
    }

    public static function allNonTransient(): self
    {
        return new self('AllNonTransient');
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

    public function equals(ProjectionMode $other): bool
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
