<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Prooph\EventStore\Exception\InvalidArgumentException;

/**
 * A structure referring to a potential logical record position
 * in the Event Store transaction file.
 */
class Position
{
    /**
     * Position representing the start of the transaction file
     * @var Position
     */
    private $start;
    /**
     * Position representing the end of the transaction file
     * @var Position
     */
    private $end;
    /**
     * The commit position of the record
     * @var int
     */
    private $commitPosition;
    /**
     * The prepare position of the record.
     * @var int
     */
    private $preparePosition;

    public function __construct(int $commitPosition, int $preparePosition)
    {
        if ($commitPosition < $preparePosition) {
            throw new InvalidArgumentException('The commit position cannot be less than the prepare position');
        }

        $this->start = new Position(0, 0);
        $this->end = new Position(-1, -1);

        $this->commitPosition = $commitPosition;
        $this->preparePosition = $preparePosition;
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
}
