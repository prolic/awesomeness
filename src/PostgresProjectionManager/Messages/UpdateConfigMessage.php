<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Messages;

use Prooph\EventStore\ProjectionManagement\ProjectionConfig;

class UpdateConfigMessage implements Message
{
    /** @var string */
    private $name;
    /** @var ProjectionConfig */
    private $config;

    public function __construct(string $name, ProjectionConfig $config)
    {
        $this->name = $name;
        $this->config = $config;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function config(): ProjectionConfig
    {
        return $this->config;
    }

    public function messageName(): string
    {
        return 'UpdateConfigMessage';
    }
}
