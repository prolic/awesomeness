<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Messages;

class UpdateQueryMessage implements Message
{
    /** @var string */
    private $name;
    /** @var string */
    private $type;
    /** @var string */
    private $query;
    /** @var bool */
    private $emitEnabled;

    public function __construct(string $name, string $type, string $query, bool $emitEnabled)
    {
        $this->name = $name;
        $this->type = $type;
        $this->query = $query;
        $this->emitEnabled = $emitEnabled;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function query(): string
    {
        return $this->query;
    }

    public function emitEnabled(): bool
    {
        return $this->emitEnabled;
    }

    public function messageName(): string
    {
        return 'UpdateQueryMessage';
    }
}
