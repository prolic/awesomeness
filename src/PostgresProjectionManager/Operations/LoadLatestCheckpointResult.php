<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Operations;

/** @internal */
class LoadLatestCheckpointResult
{
    /** @var array */
    private $state;
    /** @var array */
    private $streamPositions;

    public function __construct(array $state, array $streamPositions)
    {
        $this->state = $state;
        $this->streamPositions = $streamPositions;
    }

    public function state(): array
    {
        return $this->state;
    }

    public function streamPositions(): array
    {
        return $this->streamPositions;
    }
}
