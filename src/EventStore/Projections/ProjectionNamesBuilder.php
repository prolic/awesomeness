<?php

declare(strict_types=1);

namespace Prooph\EventStore\Projections;
use Prooph\EventStore\ProjectionManagement\QuerySourcesDefinition;

/** @internal */
class ProjectionNamesBuilder
{
    public const ProjectionsStreamPrefix = '$projections-';
    public const ProjectionsControlStream = '$projections-$control';
    public const ProjectionsMasterStream = '$projections-$master';
    public const ProjectionsRegistrationStream = '$projections-$all';

    private const ProjectionsControlStreamPrefix = '$projections-$'; //@todo unused so far
    private const ProjectionsStateStreamSuffix = '-result';
    private const ProjectionCheckpointStreamSuffix = '-checkpoint';
    private const ProjectionEmittedStreamSuffix = '-emittedstreams';
    private const ProjectionOrderStreamSuffix = '-order';
    private const ProjectionPartitionCatalogStreamSuffix = '-partitions';
    private const CategoryCatalogStreamNamePrefix = '$category-'; //@todo unused so far

    /** @var string */
    private $name;
    /** @var QuerySourcesDefinition */
    private $sources;
    /** @var string */
    private $resultStreamName;
    /** @var string */
    private $partitionCatalogStreamName;
    /** @var string */
    private $checkpointStreamName;
    /** @var string */
    private $orderStreamName;
    /** @var string */
    private $emittedStreamsName;
    /** @var string */
    private $emittedStreamsCheckpointName;
    
    public function __construct(string $name, QuerySourcesDefinition $sources)
    {
        $this->name = $name;
        $this->sources = $sources;
        $this->resultStreamName = $sources->options()->resultStreamNameOption()
            ?? self::ProjectionsStreamPrefix . $this->effectiveProjectionName() . self::ProjectionsStateStreamSuffix;
        $this->partitionCatalogStreamName = self::ProjectionsStreamPrefix . $this->effectiveProjectionName()
            . self::ProjectionPartitionCatalogStreamSuffix;
        $this->checkpointStreamName = self::ProjectionsStreamPrefix . $this->effectiveProjectionName()
            . self::ProjectionCheckpointStreamSuffix;
        $this->orderStreamName = self::ProjectionsStreamPrefix . $this->effectiveProjectionName()
            . self::ProjectionOrderStreamSuffix;
        $this->emittedStreamsName = self::ProjectionsStreamPrefix . $this->effectiveProjectionName()
            . self::ProjectionEmittedStreamSuffix;
        $this->emittedStreamsCheckpointName = self::ProjectionsStreamPrefix . $this->effectiveProjectionName()
            . self::ProjectionEmittedStreamSuffix . self::ProjectionCheckpointStreamSuffix;
    }
    
    public function effectiveProjectionName(): string
    {
        return $this->name;
    }
    
    public function resultStreamName(): string
    {
        return $this->resultStreamName;
    }

    public function checkpointStreamName(): string
    {
        return self::ProjectionsStreamPrefix . $this->effectiveProjectionName() . self::ProjectionCheckpointStreamSuffix;
    }

    public function emittedStreamsName(): string
    {
        return $this->emittedStreamsName;
    }

    public function emittedStreamsCheckpointName(): string
    {
        return $this->emittedStreamsCheckpointName;
    }
}
