<?php

declare(strict_types=1);

namespace Prooph\EventStore\ProjectionManagement;

final class ProjectionDefinition
{
    /** @var string */
    private $name;
    /** @var string */
    private $query;
    /** @var bool */
    private $emitEnabled;
    /** @var array */
    private $definition;
    /** @var array */
    private $outputConfig;

    public function __construct(string $name, string $query, bool $emitEnabled, array $definition, array $outputConfig)
    {
        $this->name = $name;
        $this->query = $query;
        $this->emitEnabled = $emitEnabled;
        $this->definition = $definition;
        $this->outputConfig = $outputConfig;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function query(): string
    {
        return $this->query;
    }

    public function emitEnabled(): bool
    {
        return $this->emitEnabled;
    }

    public function definition(): array
    {
        return $this->definition;
    }

    public function outputConfig(): array
    {
        return $this->outputConfig;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'query' => $this->query,
            'emitEnabled' => $this->emitEnabled,
            'definition' => $this->definition,
            'outputConfig' => $this->outputConfig,
        ];
    }
}
