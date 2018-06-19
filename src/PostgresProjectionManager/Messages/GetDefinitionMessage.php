<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Messages;

class GetDefinitionMessage implements Message
{
    /** @var string */
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function messageName(): string
    {
        return 'GetDefinitionMessage';
    }
}
