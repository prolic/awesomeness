<?php

declare(strict_types=1);

namespace Prooph\EventStore\ProjectionManagement;

final class QuerySourcesDefinition
{
    /** @var bool */
    private $allStreams;
    /** @var string[] */
    private $categories;
    /** @var string[] */
    private $streams;
    /** @var string */
    private $catalogStream;
    /** @var bool */
    private $allEvents;
    /** @var string[] */
    private $events;
    /** @var bool */
    private $byStreams;
    /** @var bool */
    private $byCustomPartitions;
    /** @var int|null */
    private $limitingCommitPosition;
    /** @var QuerySourcesDefinitionOptions */
    private $options;

    public function __construct(
        bool $allStreams,
        array $categories,
        array $streams,
        string $catalogStream,
        bool $allEvents,
        array $events,
        bool $byStreams,
        bool $byCustomPartitions,
        ?int $limitingCommitPosition,
        QuerySourcesDefinitionOptions $options
    ) {
        $this->allStreams = $allStreams;
        $this->categories = $categories;
        $this->streams = $streams;
        $this->catalogStream = $catalogStream;
        $this->allEvents = $allEvents;
        $this->events = $events;
        $this->byStreams = $byStreams;
        $this->byCustomPartitions = $byCustomPartitions;
        $this->limitingCommitPosition = $limitingCommitPosition;
        $this->options = $options;
    }

    public static function fromArray(array $definition): QuerySourcesDefinition
    {
        return new self(
            $definition['allStreams'],
            $definition['categories'],
            $definition['streams'],
            $definition['catalogStream'] ?? '',
            $definition['allEvents'],
            $definition['events'],
            $definition['byStreams'],
            $definition['byCustomPartitions'] ?? false,
            $definition['limitingCommitPosition'] ?? null,
            QuerySourcesDefinitionOptions::fromArray($definition['options'])
        );
    }

    public function allStreams(): bool
    {
        return $this->allStreams;
    }

    public function categories(): array
    {
        return $this->categories;
    }

    public function streams(): array
    {
        return $this->streams;
    }

    public function catalogStream(): string
    {
        return $this->catalogStream;
    }

    public function allEvents(): bool
    {
        return $this->allEvents;
    }

    public function events(): array
    {
        return $this->events;
    }

    public function byStreams(): bool
    {
        return $this->byStreams;
    }

    public function byCustomPartitions(): bool
    {
        return $this->byCustomPartitions;
    }

    public function limitingCommitPosition(): ?int
    {
        return $this->limitingCommitPosition;
    }

    public function options(): QuerySourcesDefinitionOptions
    {
        return $this->options;
    }

    public function toArray(): array
    {
        return [
            'allStreams' => $this->allStreams,
            'categories' => $this->categories,
            'streams' => $this->streams,
            'catalogStream' => $this->catalogStream,
            'allEvents' => $this->allEvents,
            'events' => $this->events,
            'byStreams' => $this->byStreams,
            'byCustomPartitions' => $this->byCustomPartitions,
            'limitingCommitPosition' => $this->limitingCommitPosition,
            'options' => $this->options->toArray(),
        ];
    }
}
