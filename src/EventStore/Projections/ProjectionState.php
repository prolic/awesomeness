<?php

declare(strict_types=1);

namespace Prooph\EventStore\Projections;

/** @internal */
class ProjectionState
{
    public const OPTIONS = [
        'Initial' => 0x80000000,
        'StartSlaveProjectionsRequested' => 0x1,
        'LoadStateRequested' => 0x2,
        'StateLoaded' => 0x4,
        'Subscribed' => 0x8,
        'Running' => 0x10,
        'Stopping' => 0x40,
        'Stopped' => 0x80,
        'FaultedStopping' => 0x100,
        'Faulted' => 0x200,
        'CompletingPhase' => 0x400,
        'PhaseCompleted' => 0x800,
    ];

    public const Initial = 0x80000000;
    public const StartSlaveProjectionsRequested = 0x1;
    public const LoadStateRequested = 0x2;
    public const StateLoaded = 0x4;
    public const Subscribed = 0x8;
    public const Running = 0x10;
    public const Stopping = 0x40;
    public const Stopped = 0x80;
    public const FaultedStopping = 0x100;
    public const Faulted = 0x200;
    public const CompletingPhase = 0x400;
    public const PhaseCompleted = 0x800;

    private $name;
    private $value;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->value = self::OPTIONS[$name];
    }

    public static function initial(): self
    {
        return new self('Initial');
    }

    public static function startSlaveProjectionsRequested(): self
    {
        return new self('StartSlaveProjectionsRequested');
    }

    public static function loadStateRequested(): self
    {
        return new self('LoadStateRequested');
    }

    public static function stateLoaded(): self
    {
        return new self('StateLoaded');
    }

    public static function subscribed(): self
    {
        return new self('Subscribed');
    }

    public static function running(): self
    {
        return new self('Running');
    }

    public static function stopping(): self
    {
        return new self('Stopping');
    }

    public static function stopped(): self
    {
        return new self('Stopped');
    }

    public static function faultedStopping(): self
    {
        return new self('FaultedStopping');
    }

    public static function faulted(): self
    {
        return new self('Faulted');
    }

    public static function completingPhase(): self
    {
        return new self('CompletingPhase');
    }

    public static function phaseCompleted(): self
    {
        return new self('PhaseCompleted');
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

    public function equals(ProjectionState $other): bool
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
