<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager;

use Amp\Postgres\Pool;
use Generator;
use Prooph\EventStore\ProjectionManagement\QuerySourcesDefinition;
use SplQueue;

class EventReaderBuilder
{
    /** @var QuerySourcesDefinition */
    private $sourcesDefintion;
    /** @var CheckpointTag */
    private $checkpointTag;
    /** @var callable */
    private $checkStreamCallback;
    /** @var Pool */
    private $pool;
    /** @var SplQueue */
    private $queue;
    /** @var bool */
    private $stopOnEof;

    public function __construct(
        QuerySourcesDefinition $sourcesDefintion,
        ?CheckpointTag $checkpointTag,
        callable $checkStreamCallback,
        Pool $pool,
        SplQueue $queue,
        bool $stopOnEof
    ) {
        $this->sourcesDefintion = $sourcesDefintion;
        $this->checkpointTag = $checkpointTag;
        $this->checkStreamCallback = $checkStreamCallback;
        $this->pool = $pool;
        $this->queue = $queue;
        $this->stopOnEof = $stopOnEof;
    }

    public function buildEventReader(): Generator
    {
        if (\count($this->sourcesDefintion->streams()) === 1) {
            $streamName = $this->sourcesDefintion->streams()[0];

            if (null === $this->checkpointTag) {
                $this->checkpointTag = CheckpointTag::fromStreamPosition($streamName, -1);
            }

            yield ($this->checkStreamCallback)($streamName);

            return new StreamEventReader(
                $streamName,
                $this->checkpointTag->streamPosition($streamName),
                $this->pool,
                $this->queue,
                $this->stopOnEof
            );
        } elseif (\count($this->sourcesDefintion->streams()) > 1) {
            $streamPositions = [];

            foreach ($this->sourcesDefintion->streams() as $streamName) {
                $streamPositions[$streamName] = -1;

                yield ($this->checkStreamCallback)($streamName);
            }

            if (null === $this->checkpointTag) {
                $this->checkpointTag = CheckpointTag::fromStreamPositions($streamPositions);
            }

            return new MultiStreamEventReader(
                $streamPositions,
                $this->pool,
                $this->queue,
                $this->stopOnEof
            );
        }

        // @todo implement
        throw new \RuntimeException('Not implemented');
    }

    public function checkpointTag(): CheckpointTag
    {
        return $this->checkpointTag;
    }
}
