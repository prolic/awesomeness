<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Messages;

use Prooph\EventStore\Internal\Principal;

class CreateProjectionMessage implements Message
{
    /** @var string */
    private $mode;
    /** @var string */
    private $name;
    /** @var string */
    private $query;
    /** @var bool */
    private $enabled;
    /** @var string */
    private $type;
    /** @var bool */
    private $checkpoints;
    /** @var bool */
    private $emit;
    /** @var bool */
    private $trackEmittedStreams;
    /** @var Principal */
    private $runAs;

    public function __construct(
        string $mode,
        string $name,
        string $query,
        Principal $runAs,
        bool $enabled,
        string $type,
        bool $checkpoints = true,
        bool $emit = false,
        bool $trackEmittedStreams = false
    ) {
        $this->mode = $mode;
        $this->name = $name;
        $this->query = $query;
        $this->runAs = $runAs;
        $this->enabled = $enabled;
        $this->type = $type;
        $this->checkpoints = $checkpoints;
        $this->emit = $emit;
        $this->trackEmittedStreams = $trackEmittedStreams;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function query(): string
    {
        return $this->query;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function checkpoints(): bool
    {
        return $this->checkpoints;
    }

    public function emit(): bool
    {
        return $this->emit;
    }

    public function trackEmittedStreams(): bool
    {
        return $this->trackEmittedStreams;
    }

    public function runAs(): Principal
    {
        return $this->runAs;
    }

    public function messageName(): string
    {
        return 'CreateProjectionMessage';
    }
}
