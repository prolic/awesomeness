<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager;

use Monolog\Handler\AbstractProcessingHandler;

/** @internal */
final class EchoHandler extends AbstractProcessingHandler
{
    protected function write(array $record)
    {
        echo (string) $record['formatted'];
    }
}
