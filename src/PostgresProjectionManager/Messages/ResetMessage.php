<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Messages;

class ResetMessage
{
    /** @var string */
    private $name;
    /** @var string|null */
    private $runAs;

    public function __construct(string $name, ?string $runAs)
    {
        $this->name = $name;
        $this->runAs = $runAs;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function runAs(): ?string
    {
        return $this->runAs;
    }
}
