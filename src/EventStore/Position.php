<?php

declare(strict_types=1);

namespace Prooph\EventStore;

/** @todo delete this?? */
class Position
{
    /** @var Position */
    private $start;
    /** @var Position */
    private $end;
    /** @var int */
    private $commitPosition;
    /** @var int */
    private $preparePosition;

    /**
     * Constructs a position with the given commit and prepare positions.
     * It is not guaranteed that the position is actually the start of a
     * record in the transaction file.
     *
     * The commit position cannot be less than the prepare position.
     */
    public function __construct(int $commitPosition, int $preparePosition)
    {
        if ($commitPosition < $preparePosition) {
            throw new \InvalidArgumentException('The commit position cannot be less than the prepare position');
        }

        $this->commitPosition = $commitPosition;
        $this->preparePosition = $preparePosition;

        $this->start = new self(0, 0);
        $this->end = new self(-1, -1);
    }

    public function start(): Position
    {
        return $this->start;
    }

    public function end(): Position
    {
        return $this->end;
    }

    public function commitPosition(): int
    {
        return $this->commitPosition;
    }

    public function preparePosition(): int
    {
        return $this->preparePosition;
    }

    public function lt(Position $position): bool
    {
        return $this->commitPosition < $position->commitPosition
            || ($this->commitPosition === $position->commitPosition && $this->preparePosition < $position->preparePosition);
    }

    public function lte(Position $position): bool
    {
        return $this->lt($position) || $this->equals($position);
    }

    public function gt(Position $position): bool
    {
        return $this->commitPosition > $position->commitPosition
            || ($this->commitPosition === $position->commitPosition && $this->preparePosition > $position->preparePosition);
    }

    public function gte(Position $position): bool
    {
        return $this->gt($position) || $this->equals($position);
    }

    public function equals(Position $position): bool
    {
        return $this->commitPosition === $position->commitPosition && $this->preparePosition === $position->preparePosition;
    }

    public function notEquals(Position $position): bool
    {
        return ! $this->equals($position);
    }
}
