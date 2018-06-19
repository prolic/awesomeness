<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Messages;

class ResetMessage implements Message
{
    /** @var string */
    private $name;
    /** @var string|null */
    private $enableRunAs;

    public function __construct(string $name, ?string $runAs)
    {
        $this->name = $name;
        $this->enableRunAs = $runAs;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function enableRunAs(): ?string
    {
        return $this->enableRunAs;
    }

    public function messageName(): string
    {
        return 'ResetMessage';
    }
}
