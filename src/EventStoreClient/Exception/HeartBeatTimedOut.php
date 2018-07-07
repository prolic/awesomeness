<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

use Amp\TimeoutException;

class HeartBeatTimedOut extends TimeoutException implements Exception
{
    public function __construct(string $message = 'Heartbeat timed out')
    {
        parent::__construct($message);
    }
}
