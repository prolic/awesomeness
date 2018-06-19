<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Messages;

interface Message
{
    public function name(): string;

    public function messageName(): string;
}
