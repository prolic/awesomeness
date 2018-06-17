<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Operations;

use Prooph\EventStore\ProjectionManagement\ProjectionConfig;
use Prooph\EventStore\Projections\ProjectionMode;

class LoadConfigResult
{
    /** @var ProjectionConfig */
    private $config;
    /** @var string */
    private $query;
    /** @var ProjectionMode */
    private $mode;
    /** @var bool */
    private $enabled;
    /** @var int */
    private $projectionEventNumber;

    public function __construct(
        ProjectionConfig $config,
        string $query,
        ProjectionMode $mode,
        bool $enabled,
        int $projectionEventNumber
    ) {
        $this->config = $config;
        $this->query = $query;
        $this->mode = $mode;
        $this->enabled = $enabled;
        $this->projectionEventNumber = $projectionEventNumber;
    }

    public function config(): ProjectionConfig
    {
        return $this->config;
    }

    public function query(): string
    {
        return $this->query;
    }

    public function mode(): ProjectionMode
    {
        return $this->mode;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function projectionEventNumber(): int
    {
        return $this->projectionEventNumber;
    }
}
