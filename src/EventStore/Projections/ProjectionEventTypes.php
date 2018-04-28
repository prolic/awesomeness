<?php

declare(strict_types=1);

namespace Prooph\EventStore\Projections;

/** @internal */
class ProjectionEventTypes
{
    public const ProjectionCheckpoint = '$ProjectionCheckpoint';
    public const PartitionCheckpoint = '$Checkpoint';
    public const StreamTracked = '$StreamTracked';

    public const ProjectionCreated = '$ProjectionCreated';
    public const ProjectionDeleted = '$ProjectionDeleted';
    public const ProjectionsInitialized = '$ProjectionsInitialized';
    public const ProjectionUpdated = '$ProjectionUpdated';
}
