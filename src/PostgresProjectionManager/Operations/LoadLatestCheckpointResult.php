<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Operations;
use Prooph\PostgresProjectionManager\CheckpointTag;

/** @internal */
class LoadLatestCheckpointResult
{
    /** @var array */
    private $state;
    /** @var CheckpointTag */
    private $checkpointTag;

    public function __construct(array $state, CheckpointTag $checkpointTag)
    {
        $this->state = $state;
        $this->checkpointTag = $checkpointTag;
    }

    public function state(): array
    {
        return $this->state;
    }

    public function checkpointTag(): CheckpointTag
    {
        return $this->checkpointTag;
    }
}
