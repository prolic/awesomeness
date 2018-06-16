<?php

declare(strict_types=1);

namespace Prooph\EventStore\Projections;

/** @internal */
class ProjectionNames
{
    public const ProjectionsStreamPrefix = '$projections-';
    public const ProjectionsControlStreamPrefix = '$projections-$';
    public const ProjectionsStateStreamSuffix = '-result';
    public const ProjectionCheckpointStreamSuffix = '-checkpoint';
    public const ProjectionEmittedStreamSuffix = '-emittedstreams';
    public const ProjectionOrderStreamSuffix = '-order';
    public const ProjectionPartitionCatalogStreamSuffix = '-partitions';
    public const CategoryCatalogStreamNamePrefix = '$category-';
    public const ProjectionsControlStream = '$projections-$control';
    public const ProjectionsMasterStream = '$projections-$master';
    public const ProjectionsRegistrationStream = '$projections-$all';
}
